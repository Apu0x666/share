<?php

/*
 * Скрипт, позволяющий синхронизировать пользователей из сервиса KeyCloak в базу данных PG
 * Основные принципы:
 * - Если пользователь есть в PG, но нет в KeyCloak - он помечается неактивным
 * - Если пользователя нет в PG, он добавляется из KeyCloak
 * - Если пользователь есть в PG (сравнение идёт по логину) + так же есть в KeyCloak - данные о нём обновляются (имя, статус, роль)
 *
 * - Имя таблицы откуда берётся пользователи задано по умолчанию, так же можно указывать при создании экземпляра класса
 * - Пользователи берутся из подгрупп KeyCloak (исходя из текущей иерархии), т.е. сейчас пользователи будут браться из группы arm_od, и роль будут браться из подгрупп (сейчас это guest)
 * */

class user_sync {
	//keycloak config
	protected static string $server = 'http://keycloak.ksomb.test.spb:8080';
	protected static string $client_id = 'users_sync';
	protected static string $client_secret = 'wiNfQBRdie88duemtMbqQ7UjYrpW2LLF';
	//the name of the user group we use by default (also can be set in constructor)
	protected static string $groupName = 'arm_od';
	
	//properties for curl query
	protected static string $url;
	protected static array $headers;
	protected static string $query = '';
	
	protected static $response; //curl-answer
	protected static string $access_token; //received authorization token
	protected static string $error_response; //server response in case of unsuccessful authorization
	
	protected static array $keyCloakMembers; //users received from Keycloak
	
	//PostGreSQL config
	protected static string $host = 'db.ksomb.test.spb';
	protected static string $port = '5432';
	protected static string $dbname = 'Settings';
	protected static string $user = 'users_sync';
	protected static string $password = 'users_sync';
	protected static $connPG; //connection to PG
	protected static array $PG_users; //users received from PG
	
	/**
	 *  Try auth to keycloak when creating obj
	 */
	public function __construct($groupName) {
		if (!self::auth()) {
			die('Something went wrong: '.self::$error_response);
		}
		//if auth was successful - set groupname
		self::$groupName = @$groupName;
	}
	
	/**
	 * @param string $url
	 */
	protected static function setUrl(string $url) {
		self::$url = self::$server . $url;
	}
	
	/**
	 * @param array $headers
	 */
	protected static function setHeaders(array $headers) {
		self::$headers = $headers;
	}
	
	/**
	 * @param array $query_params
	 * @param bool $secret
	 */
	protected static function setQuery(array $query_params = [], bool $secret = false) {
		//set query
		if ($secret) {
			$query_params += ['client_id' =>  self::$client_id, 'client_secret' =>  self::$client_secret];
		}
		self::$query = http_build_query($query_params);
	}
	
	/**
	 * @return mixed
	 */
	protected static function getResponse() {
		return json_decode(self::$response, true);
	}
	
	/**
	 *  Execute post method curl request
	 */
	protected static function execPost() {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL,self::$url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, self::$headers);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, self::$query);
		self::$response = curl_exec($curl);
		curl_close($curl);
	}
	
	/**
	 *  Execute get method curl request
	 */
	protected static function execGet() {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, self::$url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, self::$headers);
		self::$response = curl_exec($curl);
		curl_close($curl);
	}
	
	/**
	 * Auth user and get access-token
	 * @return bool
	 */
	protected function auth(): bool {
		self::setUrl('/auth/realms/ksomb/protocol/openid-connect/token');
		self::setHeaders([
			'Content-Type: application/x-www-form-urlencoded',
			'Accept: application/json',
			'cache-control: no-cache',
		]);
		self::setQuery(['grant_type' => 'client_credentials'], true);
		self::execPost();
		
		$response = self::getResponse();
		if (!empty($response["access_token"])) {
			self::$access_token = $response["access_token"];
		} else {
			self::$access_token = false;
			self::$error_response = $response["error"] ?? 'Can\'t connect to the server';
		}
		return (bool)((@$response["access_token"]));
	}
	
	/**
	 * Get groups list from current realm
	 * @return mixed
	 */
	protected function getGroups() {
		self::setUrl('/auth/admin/realms/ksomb/groups');
		self::setHeaders([
			"Authorization: Bearer ". self::$access_token,
			"ache-control: no-cache",
		]);
		self::execGet();
		
		return self::getResponse();
	}
	
	/**
	 * Get members for the specified group
	 * @param string $group_id
	 * @return mixed
	 */
	protected function getGroupMembers(string $group_id) {
		/*
			53274a9f-0ca3-4dd0-bda4-1b7226345cec  /arm_adm/guest
			e7ecfd82-6dae-4e2d-a3cf-e114206f7bf0  /arm_archive/guest
			ef86401d-761c-4701-b0f0-fa9ab6d4a523  /arm_od/guest
		*/
		self::setUrl('/auth/admin/realms/ksomb/groups/'.$group_id.'/members');
		self::setHeaders([
			"Authorization: Bearer ". self::$access_token,
			"ache-control: no-cache",
		]);
		self::execGet();
		
		return self::getResponse();
	}
	
	/**
	 *  Get list of members from Keycloak service
	 * @return void
	 */
	public function getKeyCloakMembers() {
		try {
				$groups = self::getGroups();
				foreach ($groups as $group) {
					if ($group['name'] == self::$groupName && count($group['subGroups']) > 0) {
						
						foreach ($group['subGroups'] as $sub_group) {
							$members = self::getGroupMembers($sub_group['id']);
							foreach ($members as $member) {
								self::$keyCloakMembers[] = [
									'role' => $sub_group['name'],
									'login' => $member['username'],
									'name' => $member['firstName'],
									'active' => ($member['enabled']) ? 'true' : 'false',
								];
							}
						}
						
					}
				}
		} catch (Exception $e) {
			die('Something went wrong: '.PHP_EOL.$e->getMessage());
		}
	}
	
	/**
	 * Set connection to PostGreSQL database
	 * @return void
	 */
	public function connPG(){
		self::$connPG = @pg_connect('host='.self::$host.' port='.self::$port.' dbname='.self::$dbname.' user='.self::$user.' password='.self::$password);
		if (self::$connPG==FALSE) die("Couldn't connect to database.");
	}
	
	/**
	 * Close connection to PostGreSQL database
	 * @return void
	 */
	public function connPG_close(){
		pg_close(self::$connPG);
	}
	
	/**
	 * Get all existing users from PostGreSQL
	 * @return void
	 */
	public function getUsersFromPG() {
		$query = 'SELECT t.* FROM "Users"."Catalog" t';
		$result = pg_query(self::$connPG, $query);
		if (!$result) {
			die("Error 01. Can't get users from PostGreSQL");
		}
		self::$PG_users = pg_fetch_all($result);
	}
	
	/**
	 * Insert or Update users in PostGreSQL db, from Keycloak
	 * @return void
	 */
	public function upsertUsers(){
		$keycloak_members_flipped = [];
		$upsert_count = 0;
		foreach (self::$keyCloakMembers as $keycloak_member) {
			$keycloak_members_flipped[$keycloak_member['login']] = [
				'role' => $keycloak_member['role'],
				'name' => $keycloak_member['name'],
				'active' => $keycloak_member['active'],
			];
			//prepare data for upsert query
			$login = '\''.$keycloak_member['login'].'\'';
			$name = '\''.$keycloak_member['name'].'\'';
			$active = '\''.$keycloak_member['active'].'\'';
			$role = '\''.$keycloak_member['role'].'\'';
			
			//try insert data to new line, on duplicate "login" update line with new fields
			$query = 'INSERT INTO "Users"."Catalog" ("Login","Name","IsActive","Role")
									VALUES ('.$login.','.$name.','.$active.','.$role.')
									ON CONFLICT ("Login")
									DO
			              UPDATE SET
			                "IsActive" = '.$active.',
											"Name" = '.$name.',
									    "Role" = '.$role;
			$result = pg_query(self::$connPG, $query);
			if (!$result) {
				die("Error 02. Can't insert/update user to PostGreSQL");
			}
			$upsert_count++;
		}
		
		$user_off = 0;
		foreach (self::$PG_users as $pg_user) {
			if (!array_key_exists($pg_user["Login"], $keycloak_members_flipped)) {
				//Turn off user accounts of which are not in the received data from Keycloak
				$login = '\''.$pg_user["Login"].'\'';
				$query = 'UPDATE "Users"."Catalog" t
									SET "IsActive" = FALSE
									WHERE t."Login" = '.$login;
				$result = pg_query(self::$connPG, $query);
				if (!$result) {
					die("Error 03. Can't update user in PostGreSQL");
				}
				$user_off++;
			}
		}
		
		print('User exist in PG but not exist in Keycloak: '.$user_off.PHP_EOL.'Upsert users: '.$upsert_count);
	}
}

$user_sync = new user_sync('arm_od');
$user_sync->getKeyCloakMembers();
$user_sync->connPG();
$user_sync->getUsersFromPG();
$user_sync->upsertUsers();
$user_sync->connPG_close();