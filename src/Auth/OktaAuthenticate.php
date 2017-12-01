<?php

namespace OktaAuth\Auth;

use Cake\Auth\BaseAuthenticate;
use Cake\Http\ServerRequest;
use Cake\Http\Response;
use Cake\Http\Client;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\UnauthorizedException;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Cake\ORM\TableRegistry;


class OktaAuthenticate extends BaseAuthenticate
{

    public function authenticate(ServerRequest $request, Response $response)
    {

        $authHeaderSecret = base64_encode(Configure::read('Credentials.clientId') .
                                                ":" . Configure::read('Credentials.clientSecret'));

        $http = new Client();
        $response = $http->post(Configure::read('OktaConfig.tokenUrl'), [
                'grant_type' => 'authorization_code',
                'code' => $request->getQuery('code'),
                'redirect_uri' => Configure::read('OktaConfig.redirectUrl')
            ], [
                'headers' => [
                    'Authorization' => 'Basic: ' . $authHeaderSecret,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded'

                ]
            ]);

        $responseData = json_decode($response->body);
        //return $response->body;
        Log::write('debug', $responseData);

        if(property_exists($responseData, 'error')) {
            throw new BadRequestException($responseData->error);
        }

        $jwt = $responseData->access_token;

        $keys = json_decode(file_get_contents('https://'.Configure::read('Credentials.domain').'/oauth2/'.Configure::read('OktaConfig.authorizationServerId').'/v1/keys'));
        Log::write('debug', $keys);
        $keys = JWK::parseKeySet($keys);
        $decoded = JWT::decode($jwt, $keys, ['RS256']);
        Log::write('debug', $decoded);

        //compare issuer with authorizationServer
        if($decoded->iss != 'https://'.Configure::read('Credentials.domain').'/oauth2/'.Configure::read('OktaConfig.authorizationServerId')) {
            throw new UnauthorizedException(__('Issuer Mismatch Error'));
        }

        //compare client id from token with config
        if($decoded->cid != Configure::read('Credentials.clientId')) {
            throw new UnauthorizedException(__('Client ID Mismatch Error'));
        }

        //compare issued time is within now+300
        if($decoded->iat > (time()+300)) {
            throw new UnauthorizedException(__('Token was issued in the future'));
        }

        //compare expiration time has not passed (with leeway for clock error)
        if($decoded->exp < (time()-300)) {
            throw new UnauthorizedException(__('Token has expired'));
        }


        //at this point the token is valid, log in the user...
        Log::write('debug', "Everything was good...let's log you in...");


        //find local user if exists, or create local user if doesn't exist
        //find by okta user id
        $users = TableRegistry::get('OktaAuth.Users');

        $idTokenDecoded = JWT::decode($responseData->id_token, $keys, ['RS256']);
        Log::write('debug', $idTokenDecoded);

        //check if user exists in application by Okta ID
        $user = $users->findByOktaUserId($decoded->uid)->first();
        if($user) {
            $updatedData = ['email' => $idTokenDecoded->email, 'name' => $idTokenDecoded->name];
            $user = $users->patchEntity($user, $updatedData);
            $user = $users->save($user);
            return $user;
        }

        //If user doesn't exist by Okta ID, check email, if user is found update okta id field
        $userByEmail = $users->findByEmail($idTokenDecoded->email)->first();
        if($userByEmail) {
            $updatedData = ['okta_user_id' => $decoded->uid, 'name' => $idTokenDecoded->name];
            $userByEmail = $users->patchEntity($userByEmail, $updatedData);
            $userByEmail = $users->save($userByEmail);
            return $userByEmail;
        }

        //user doesn't exist in the application, we'll create the user
        $newData = ['okta_user_id' => $decoded->uid, 'email' => $idTokenDecoded->email, 'name' => $idTokenDecoded->name];
        $user = $users->newEntity($newData);
        if($user = $users->save($user)) {
            return $user;
        }

        return false;

    }
}

?>