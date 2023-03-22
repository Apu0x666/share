<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class KeyCloakController extends Controller
{

    protected static $server;
    protected static $client_id;
    protected static $client_secret;
    protected static $realm;

    //properties for curl query
    protected static $url;
    protected static $headers;
    protected static $query = '';

    protected static $response; //curl-answer
    protected static $access_token; //received authorization token
    protected static $error_response; //server response in case of unsuccessful authorization

    protected static $keyCloakMembers; //users received from Keycloak

    private static function setBaseParams() {
        self::setServer(env('KEYCLOAK_BASE_URL'));
        self::setClientId(env('KEYCLOAK_CLIENT_ID'));
        self::setClientSecret(env('KEYCLOAK_CLIENT_SECRET'));
        self::setRealm(env('KEYCLOAK_REALM'));
    }

    public static function getKeyCloakUsers() {
        self::setBaseParams();

        self::setUrl('/realms/'.self::$realm.'/protocol/openid-connect/token');
        if (!self::auth()) {
            die('Something went wrong: '.self::$error_response);
        }

        $users = self::getUsers();
        $answer = []; //массив для возврата сформированных пользователей
        foreach ($users as $user) {
            $answer[] = [
                'value' => $user['id'],
                'text' => $user['lastName'].' '.$user['firstName'],
            ];
        }

        return $answer;
    }

    /**
     * Auth user and get access-token
     * @return bool
     */
    protected static function auth(): bool {
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
     * @param string $url
     */
    protected static function setUrl(string $url) {
        self::$url = self::$server . $url;
    }

    /**
     * @param string $server
     */
    public static function setServer(string $server): void {
        self::$server = $server;
    }

    /**
     * @param string $client_id
     */
    public static function setClientId(string $client_id): void {
        self::$client_id = $client_id;
    }

    /**
     * @param string $client_secret
     */
    public static function setClientSecret(string $client_secret): void {
        self::$client_secret = $client_secret;
    }

    /**
     * @param string $realm
     */
    public static function setRealm(string $realm): void {
        self::$realm = $realm;
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
     * Get users list from current realm
     * @return mixed
     */
    protected static function getUsers() {
        self::setUrl('admin/realms/'.self::$realm.'/users');
        self::setHeaders([
            "Authorization: Bearer ". self::$access_token,
            "cache-control: no-cache",
        ]);
        self::execGet();
        return self::getResponse();
    }

    /**
     * Get groups list from current realm
     * @return mixed
     */
    public static function getGroups() {
        self::setUrl('admin/realms/'.self::$realm.'/groups');
        self::setHeaders([
            "Authorization: Bearer ". self::$access_token,
            "cache-control: no-cache",
        ]);
        self::execGet();

        return self::getResponse();
    }

    /**
     * Get groups list for current user
     * @return mixed
     */
    public static function getCurrentUserGroups() {
        self::setBaseParams();

        self::setUrl('/realms/'.self::$realm.'/protocol/openid-connect/token');
        if (!self::auth()) {
            die('Something went wrong: '.self::$error_response);
        }

        $current_user_id = self::getCurrentUser();
        self::setUrl('admin/realms/'.self::$realm.'/users/'.$current_user_id.'/groups');

        self::setHeaders([
            "Authorization: Bearer ". self::$access_token,
            "cache-control: no-cache",
        ]);
        self::execGet();
        return self::getResponse();
    }

    public static function getCurrentUser() {
        return Auth::guest()?null:auth()->user()->getKey();
    }

    public static function getCurrentUserData() {
        self::setBaseParams();

        self::setUrl('/realms/'.self::$realm.'/protocol/openid-connect/token');
        if (!self::auth()) {
            die('Something went wrong: '.self::$error_response);
        }

        $users = self::getUsers();
        $result = [];
        foreach ($users as $user) {
            if ($user['id'] == self::getCurrentUser()) {
                    $result = [
                        'fio' => $user['lastName'].' '.$user['firstName'],
                        'email' => $user['email'] ?? NULL,
                    ];
            }
        }
        return ($result) ?: 'User doesn\'t find';
    }

    public static function createUserGroup($name) {
        self::setBaseParams();

        self::setUrl('/realms/'.self::$realm.'/protocol/openid-connect/token');
        if (!self::auth()) {
            die('Something went wrong: '.self::$error_response);
        }

        self::setUrl('/realms/'.self::$realm.'/groups');
        self::setHeaders([
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
            'cache-control: no-cache',
        ]);
        self::setQuery(["name" => $name, "path" => '/'.$name]);
        self::execPost();

        print_r(self::getResponse());
        die();

        // admin/realms/heroes/groups/
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
}
