<?php

/**
 * Partial password implementation for Yii 2.
 * @author Paweł Bizley Brzozowski
 * @version 1.0
 * @license http://opensource.org/licenses/Apache-2.0
 * 
 * https://github.com/bizley-code/yii2-partial-password
 */

namespace bizley\partialpassword\samples\controllers;

use Yii;
use yii\web\Controller;

/**
 * This is the sample controller for the views with PartialPassword widget.
 * The first action 'login' checks if user account with the given username 
 * exists. If so, the username is saved in session, and user is redirected to 
 * 'password' action.
 * Second action verifies the partial password and logs in the user.
 * It would be wise to limit the number of tries for the password entry as well.
 * 
 * Copy this code to your controller.
 */
class SiteController extends Controller
{
    public function actionLogin()
    {
        $model = new \bizley\partialpassword\samples\models\LoginForm();
        
        if ($model->load(Yii::$app->request->post()) && $model->process()) {
            return $this->redirect(['password']);
        }
        else {
            return $this->render('login', ['model' => $model]);
        }
    }
    
    public function actionPassword()
    {
        if (Yii::$app->session->has('username')) {
        
            $model = new \bizley\partialpassword\samples\models\PasswordForm();
            
            if ($model->load(Yii::$app->request->post()) && $model->login()) {
                return $this->redirect(['index']);
            }
            else {
                return $this->render('password', ['model' => $model]);
            }
        }
        else {
            return $this->redirect(['login']);
        }
    }
}