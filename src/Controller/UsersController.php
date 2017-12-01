<?php
namespace OktaAuth\Controller;

use OktaAuth\Controller\AppController;
use Cake\Event\Event;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Cake\Log\Log;
use Cake\Core\Configure;



class UsersController extends AppController
{
    public function initialize()
    {
        parent::initialize();
        //$this->Auth->allow(['logout']);
    }

//add your new actions, override, etc here
    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        // Allow users to register and logout.
        // You should not add the "login" action to allow list. Doing so would
        // cause problems with normal functioning of AuthComponent.

    }


    public function login()
    {
        
        //if code is present request, retrieve a token
        if($this->request->getQuery('code')) {
            $user = $this->Auth->identify();
            if ($user) {
                $this->Auth->setUser($user);
                return $this->redirect($this->Auth->redirectUrl());
            } else {
                $this->Flash->error(__('Error gaining access..'));
            }
        }

        if($this->request->getQuery('id_token')) {
            $keys = json_decode(file_get_contents('https://'.Configure::read('Credentials.domain').'/oauth2/'.Configure::read('OktaConfig.authorizationServerId').'/v1/keys'));
            Log::write('debug', $keys);
            $keys = JWK::parseKeySet($keys);

            $idTokenDecoded = JWT::decode($this->request->getQuery('id_token'), $keys, ['RS256']);
            Log::write('debug', $idTokenDecoded);

        }

    }

    public function logout()
    {
        return $this->redirect($this->Auth->logout());
    }
}