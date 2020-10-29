<?php

require 'header.php';

use Restserver\Libraries\REST_Controller;

require APPPATH . '/libraries/REST_Controller.php';

/**
class that controls banks interface and how bank users react with page
extends from REST_Controller class. 
*/
class Banks extends REST_Controller {
    
    /**
    encodes usage from parent class
    @param  none
    @return none
    */
    public function __construct() {
        parent::__construct();
    }
    /**
    encodes function to get the index of user
    @param int $id, if none is given, id = 0 input number
    @return none
    */
    public function index_get($id = 0) {
        $is_valid_token = $this->authorization_token->validateToken();
        if (!empty($is_valid_token) AND $is_valid_token['status'] === TRUE) {
            if (!empty($id)) {
                $data = $this->banks_model->find(array('idbank' => $id));
            } else {
                $data = $this->banks_model->getAll();
            }
            $this->response($data, REST_Controller::HTTP_OK);
        } else {
            $this->response(['status' => FALSE, 'message' => $is_valid_token['message']], REST_Controller::HTTP_NOT_FOUND);
        }
    }
    /**
    encodes function to post index back for later usage
    @param null
    @returns null
    */
    public function index_post() {
        $is_valid_token = $this->authorization_token->validateToken();
        if (!empty($is_valid_token) AND $is_valid_token['status'] === TRUE) {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!empty($data["bank"]) && !empty($data["banker"]) && !empty($data["phone"]) && !empty($data["email"])) {
                $id = uniqid('', true);
                $password = random_string('alnum', 8);

                $this->db->trans_start();
                $this->db->trans_strict(FALSE);

                $this->banks_model->save(array(
                    'idbank' => $id,
                    'bank' => $this->security->xss_clean($data["bank"])
                        )
                );

                $this->bankers_model->save(array(
                    'bankId' => $id,
                    'idbanker' => uniqid('', true),
                    'banker' => $this->security->xss_clean($data["banker"]),
                    'phone' => $this->security->xss_clean($data["phone"]),
                    'email' => $this->security->xss_clean($data["email"]),
                    'password ' => cryptage($password)
                        )
                );
                $this->db->trans_complete();

                if ($this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                    $response = ['status' => parent::HTTP_FORBIDDEN, 'message' => 'Forbidden'];
                    $status = REST_Controller::HTTP_FORBIDDEN;
                } else {
                    $this->db->trans_commit();
                    $response = [$id];
                    $status = REST_Controller::HTTP_OK;
                }
                $this->set_response($response, $status);
            } else {
                $this->response(['Bad request'], REST_Controller::HTTP_BAD_REQUEST);
            }
        } else {
            $this->response(['status' => FALSE, 'message' => $is_valid_token['message']], REST_Controller::HTTP_NOT_FOUND);
        }
    }
    /**
    encodes function to put index of user in file
    @param int $id input number
    @return null
    */
    public function index_put($id) {

        $data = json_decode(file_get_contents('php://input'), true);
        if (!empty($data["townHall"])) {
            $id = $this->security->xss_clean($id);
            $townHall = $this->security->xss_clean($data["bank"]);

            $this->banks_model->update(array('bank' => $townHall), array('idbank' => $id));
            $this->response(['Success'], REST_Controller::HTTP_OK);
        } else {
            $this->response(['Bad Request'], REST_Controller::HTTP_BAD_REQUEST);
        }
    }
    /**
    encodes function to post banker info on front end view
    @param null
    @return null
    */
    public function banker_post() {
        $is_valid_token = $this->authorization_token->validateToken();
        if (!empty($is_valid_token) AND $is_valid_token['status'] === TRUE) {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!empty($data["bankId"]) && !empty($data["banker"]) && !empty($data["phone"]) && !empty($data["email"])) {
                $id = uniqid('', true);
                 $bankId = $this->security->xss_clean($data["bankId"]);
                $email = $this->security->xss_clean($data["email"]);
                $password = random_string('alnum', 8);

                $this->bankers_model->save(
                        array( 
                            'idbanker' =>$id,
                            'bankId' => $bankId,                           
                            'banker' => $this->security->xss_clean($data["banker"]),
                            'phone' => $this->security->xss_clean($data["phone"]),
                            'email' => $email,
                            'password ' => cryptage($password)
                ));

                $message = "<p>Cher partenaire</p>"
                        . "<p>Votre compte CdSSS vient d'être créée.<br>"
                        . "Veuillez trouver ci-dessous vos paramètres de connexion </p>"
                        . "<p>Adresse e-mail : <strong>$email</strong><br>"
                        . "Mot de passe: <strong>$password</strong></p>"
                        . "<p>Pour vous connecter, vous devriez aller à l'adresse suivante : </p>"
                        . "<p><a href='https://dashboard.csss-ci.com/' style='display: inline-block; text-decoration: none; font-weight: 700;'>https://dashboard.csss-ci.com/</a></p>"
                        . "<p><em>Si vous rencontrez un problème, n'hésitez pas à contacter l'administrateur.</em></p>"
                        . "<p>L'Administrateur</p>";

                $config = array();
                $config['protocol'] = 'mail';
                $config['mailpath'] = '/usr/sbin/sendmail';
                $config['charset'] = 'utf-8';
                $config['mailtype'] = 'html';
                $config['newline'] = "\r\n";
                $config['wordwrap'] = TRUE;
                $this->email->initialize($config);

                $this->email->clear();
                $this->email->from('no-reply@csss-ci.com', 'CSSS-CARE', 'dashboard@csss-ci.com');
                $this->email->to($email);
                  $this->email->bcc('jacquagnui@gmail.com');
                $this->email->subject("CSSS-CARE - Les Paramètres de connexion");
                $this->email->message($message);
                $this->email->send();


                $this->set_response([$id], REST_Controller::HTTP_OK);
            } else {
                $this->response(['Bad request'], REST_Controller::HTTP_BAD_REQUEST);
            }
        } else {
            $this->response(['status' => FALSE, 'message' => $is_valid_token['message']], REST_Controller::HTTP_NOT_FOUND);
        }
    }
    /**
    encodes function to receive information from banker user
    @param int $id, if none, null input number
    @return null
    */
    public function bankers_get($id = null) {
        $is_valid_token = $this->authorization_token->validateToken();
        if (!empty($is_valid_token) AND $is_valid_token['status'] === TRUE) {
            $id = isset($id) ? $this->security->xss_clean($id) : $this->authorization_token->userData()->town;

            $data = $this->bankers_model->find(array('bankId' => $id));

            $this->response($data, REST_Controller::HTTP_OK);
        } else {
            $this->response(['status' => FALSE, 'message' => $is_valid_token['message']], REST_Controller::HTTP_NOT_FOUND);
        }
    }
    /**
    encodes function to give options based on index
    @param null
    @returns object method from $this
    */
    public function index_options() {
        return $this->response(NULL, REST_Controller::HTTP_OK);
    }

}
