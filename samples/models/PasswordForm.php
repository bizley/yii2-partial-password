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
 * This is the sample password model for the views with PartialPassword widget.
 * The form here gets a random pattern from database saved for the user and 
 * presents him the password form.
 * Partial password is validated and user can log in.
 * 
 * Copy this code to your models or use it properly namespaced.
 */
class PasswordForm extends Model
{

    public $password;
    public $pattern;
    
    private $_user;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['pattern', 'required'],
            ['password', 'validatePassword'],
        ];
    }
    
    /**
     * Validates the partial password.
     * This method serves as the inline validation for password.
     * Notice that password comes as array but validating method requires string.
     *
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePassword($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();
            if (!$user || !$user->validatePartialPassword($this->pattern, implode('', $this->password))) {
                $this->addError($attribute, 'Incorrect password.');
            }
        }
    }
    
    /**
     * Logs in a user using the provided username and password.
     *
     * @return boolean whether the user is logged in successfully
     */
    public function login()
    {
        if ($this->validate()) {
            return Yii::$app->user->login($this->getUser());
        } else {
            return false;
        }
    }
    
    /**
     * Finds user by session's 'username'.
     *
     * @return User|null
     */
    protected function getUser()
    {
        if ($this->_user === null) {
            $this->_user = User::findByUsername(Yii::$app->session->get('username'));
        }
        return $this->_user;
    }

    /**
     * Returns random partial password pattern saved with user.
     * 
     * @return integer
     */
    public function getPattern()
    {
        $user = $this->getUser();        
        if ($user) {
            return (int)$user->getRandomPartialPattern()['pattern'];
        }        
        return null;
    }
}