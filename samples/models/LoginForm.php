<?php

/**
 * Partial password implementation for Yii 2.
 * @author Paweł Bizley Brzozowski
 * @version 1.0
 * @license http://opensource.org/licenses/Apache-2.0
 * 
 * https://github.com/bizley-code/yii2-partial-password
 */

namespace bizley\partialpassword\samples\models;

use app\models\User;
use Yii;
use yii\base\Model;

/**
 * This is the sample login model for the views with PartialPassword widget.
 * The form here only checks if user account with the given username exists.
 * If so, we can get random pattern from database saved for the user and present 
 * him the password form.
 * 
 * Copy this code to your models or use it properly namespaced.
 */
class LoginForm extends Model
{

    public $username;
    
    private $_user;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['username', 'required'],
        ];
    }
    
    /**
     * Sets the username in session.
     *
     * @return boolean whether the username is set
     */
    public function process()
    {
        if ($this->validate()) {
            if ($this->getUser()) {
                Yii::$app->session->set('username', $this->username);
                return true;
            }
        }
        return false;
    }
    
    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    protected function getUser()
    {
        if ($this->_user === null) {
            $this->_user = User::findByUsername($this->username);
        }
        return $this->_user;
    }
}