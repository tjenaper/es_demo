<?php

class User {

	private $id, 
			$firstname, 
			$lastname, 
			$email, 
			$phone, 
			$address, 
			$latitude, 
			$longitude,
			$premium = FALSE,
			$noOfAds;

	private static $user = FALSE;

	function __construct($id){
		//$cleadId tvättas via clean() i DB-klassen. 
		$cleanId = DB::clean($id);
		$sql = "SELECT firstname, lastname, email, phone, address, latitude, longitude, premium
				FROM user
				WHERE id = ".$cleanId;

		//sql-frågan skickas iväg till databasen via DB-klassens query-metod.
		//TRUE skickas med för att vi bara får tillbaka en rad.
		//$data är en array som innehåller raden från databasen. 
		$data = DB::query($sql, TRUE);
		
		$this->id 				= $cleanId;
		$this->firstname 		= $data["firstname"];
		$this->lastname 		= $data["lastname"];
		$this->email 			= $data["email"];
		$this->phone 			= $data["phone"];
		$this->address 		 	= $data["address"];
		$this->latitude 		= $data["latitude"];
		$this->longitude 		= $data["longitude"];
		$this->noOfAds			= self::countUserAds($this->id);

		if($data['premium'] == 1) {
			$this->premium = TRUE;
		}
	
	}

	//Om man inte har angett en metod i URL körs fallback-metoden.
	public static function fallback() { 
		return self::dashboard();		
 	}

 	//Magisk get för att Twig ska kunna komma åt privata properties i klassen.
	function __get($var) {
		if ($this->$var) {
			return $this->$var;
		}
	}

	//Magisk isset behövs för att Twig ska kunna använda magisk get.
	function __isset($var) { 
		if ($this->$var) {
			return TRUE; 
		}
		return FALSE; 
	}

	//Skriver ut formulär för att skapa ny användare
	public static function newUserForm() {
		$output = ['browserTitle' => 'Skapa användare', 'page' => 'user.newuserform.twig'];

		return $output;
	}

	//$input kommer från POST-fälten i user.newuserform.twig.
	public static function saveNewUser($input) {
		$cleanInput = DB::clean($input);

		$firstname 		= $cleanInput['firstname'];
		$lastname 		= $cleanInput['lastname'];
		$address  		= $cleanInput['address'];
		$latitude 		= $cleanInput['latitude'];
		$longitude 		= $cleanInput['longitude'];
		$email 			= $cleanInput['email'];
		$scrambledPassword = hash_hmac("sha1", $cleanInput["password"], "dont put baby in the corner");
		
		$sql = "INSERT INTO user 
				(firstname, lastname, address, latitude, longitude, email, password)
				VALUES
				('$firstname', '$lastname', '$address', '$latitude', '$longitude', '$email', '$scrambledPassword')
		";

		$data = DB::query($sql);

		//Om vi har lyckats skapa en användare (lägga in denna i databasen) returneras $data som TRUE och 
		//användaren skickas till /user annars visas databaserror. 
		if($data) {
			header('Location: //'.ROOT.'/user');	
		}
	}

	public static function login($input){

		if(!isset($_SESSION['everythingSthlm']['ref_url'])) {
			$_SESSION['everythingSthlm']['ref_url'] = $_SERVER['HTTP_REFERER'];
		}

		$cleanInput = DB::clean($input);
		$scrambledPassword = hash_hmac("sha1", $cleanInput["password"], "dont put baby in the corner");
		$sql = "SELECT id
				FROM user
				WHERE email = '".$cleanInput["email"]."'
				AND password = '".$scrambledPassword."'
				";
		//TRUE gör att man bara får tillbaka en rad
		$data = DB::query($sql, TRUE); 

		if($data){
			$_SESSION["everythingSthlm"]["userId"] = $data["id"];
			//Skapa en instans av User-klassen. Constructorn körs och hämtar info om användaren. 
			self::$user = new User($data["id"]);

			$output = ['redirect_url' => $_SESSION['everythingSthlm']['ref_url']];
			unset($_SESSION['everythingSthlm']['ref_url']);
			
		}

		else {
			$output = ['redirect_url' => '/user/loginform/'];
		}

		return $output;

	}

	//Kollar om användaren är inloggad eller inte. Om man är inloggad finns möjlighet att plocka ut 
	//användarobjektet annars skickas man t inloggningsformuläret. Elseif ska bara säga nej du är inte 
	//inloggad = får ej gå vidare i koden. 
	public static function checkLoginStatus($sendToLogin = TRUE) {

		//Finns ingen användare och vi vill skicka anv. till login-form:
		if(!isset($_SESSION["everythingSthlm"]["userId"]) && $sendToLogin) {
			$_SESSION['everythingSthlm']['ref_url'] = $_SERVER['REQUEST_URI'];
			//$output = ['redirect_url' => '/user/loginform'];
			header('Location: /user/loginform');
		} 
		//Finns ingen anv. och $sendToLogin är FALSE
		elseif(!isset($_SESSION["everythingSthlm"]["userId"]) && !$sendToLogin) {
			$output = FALSE;
		} 
		// Annars finns ett användar-id i sessionen från vilket vi skapar ett nytt användarobjekt som vi också lagrar i klassen User
		else {
			$id = $_SESSION["everythingSthlm"]["userId"];
			if(!self::$user) {
				self::$user = new User($id);
			}
			$output = self::$user;
		}
		return $output;
	}
	
	//Skickar info så vi kan skriva ut loginformuläret.
	public static function loginForm() {
		
		if(!isset($_SESSION['everythingSthlm']['ref_url'])) {
			$_SESSION['everythingSthlm']['ref_url'] = $_SERVER['HTTP_REFERER'];
		}
		$output = ['browserTitle' => 'Logga in', 'page' => 'user.loginform.twig'];

		return $output;
 	}

 	//Dashboard visas på /user. Här skickar vi med user-objekt och användarens annonser. 
 	public static function dashboard() { 
		$user = self::checkLoginStatus(); 
		if($user) {
		
			$output = [
			'browserTitle' 		=> 'Hej och välkommen '.$user->firstname.'!', 
			'page' 				=> 'user.dashboard.twig',
			'user' 				=> $user,
			'ads' 				=> Ads::getUserAds(),
			'interestingAds' 	=> Ads::getInterestingAds()
			];
		}
		else {
			$output = [
			'page' => 'home.twig'
			];
		}

		return $output;
 	}

 	//Loggar ut användaren.
 	public static function logOut() {

 		//$_SESSION['everythingSthlm']['userId'] = FALSE;
 		session_destroy();
 		self::$user = FALSE;

 		header('Location: //'.ROOT); 
 	}

 	private static function countUserAds($userId) {

 		$cleanUserId = DB::clean($userId);
 		$sql = "SELECT count(*) as count FROM user_has_created_ads WHERE user_id = ".$cleanUserId;
 		$data = DB::query($sql, TRUE);

 		$output = $data['count'];
 		return $output;

 	}

 	//Skriver ut formulär för att skapa ny användare
	public static function premiumForm() {
		$output = [
					'browserTitle' => 'Uppgradera till Premium', 
					'page' => 'user.premiumform.twig'
		];

		return $output;
	}


	public static function setUserPremium() {

		$user = self::checkLoginStatus();

		$sql = "UPDATE user SET premium = 1 WHERE id = ".$user->id;
		$data = DB::query($sql);

		$output = ['redirect_url' => '../'];
		return $output;
	}

	public static function unsetUserPremium() {

		$user = self::checkLoginStatus();

		$sql = "UPDATE user SET premium = 0 WHERE id = ".$user->id;
		$data = DB::query($sql);

		$output = ['redirect_url' => '../'];
		return $output;
	}

	public static function getAddress() {

		self::checkLoginStatus(false);

		if(self::$user) {
			$output['address'] = self::$user->address;
			$output['latitude'] = self::$user->latitude;
			$output['longitude'] = self::$user->longitude;
		}
		else {
			$output = false;
		}
		return $output;
	}

}

