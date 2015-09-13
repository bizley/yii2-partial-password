# yii2-partial-password

Partial password implementation (Yii 2 extension).

## Installation

Add the package to your composer.json:

    {
        "require": {
            "bizley/partialpassword": "*"
        }
    }

and run composer update or alternatively run composer require bizley/partialpassword

## PartialPasswordBehavior

PartialPasswordBehavior generates the partial password hashes based on raw user's password that can be used in login process - instead of typing the whole password user has to type only 
few selected characters from it.
This code generated random number of partial passwords hashes with random patterns of characters to type.

Use PartialPassword widget to render the characters fields based on the selected pattern in your view.

### Usage:

Add this to your user's model.

~~~
use bizley\partialpassword\behaviors\PartialPasswordBehavior;

public function behaviors()
{
    return [
       [
           'class' => PartialPasswordBehavior::className(),
           'bitsRange' => 20,
           'passwordsMin' => 5,
           'passwordsMax' => 8,
           'charactersMin' => 3,
           'charactersMax' => 5,
           'repeatDropRate' => PartialPasswordBehavior::REPEAT_DROP_SMALL,
           'tableName' => '{{%password}}',
       ],
   ];
}
~~~

### Important methods

- generatePartialHashes() - generates partial password hashes.
- savePartialHashes() - saves hashes in database (generates them first if not generated). Also optionally deletes previously saved hashes.
- deletePreviousPartialHashes() - deletes previously saved hashes.
- getPartialHashForPattern() - selects the partial password hash for the given pattern from database.
- getRandomPartialPattern() - selects a random pattern from database.
- validatePartialPassword() - validates user partial password.

### Few things to notice

(!) Note that the biggest generated pattern for active password fields cannot be greater than PHP_INT_MAX value of your environment. This is the problem only in case of really large 
values. For example, with bitsRange = 20 and charactersMax = 20 the biggest pattern is 1048575. For 40 and 40 it's 1099511627775.

(!!) Make sure that time required for generating all the passwords is not time-outing your application and also that it's not tedious for users. In case of generating many passwords it 
might be better to move the process outside the normal registration flow.

## PartialPassword widget

PartialPassword widget generates the input fields for the password characters based on the given pattern.
Pattern is saved together with the partial password hash generated from the raw user's password.

(!) Notice that bitsRange value must be the same as set for the PartialPasswordBehavior that generated the partial passwords hashes.

### Basic usage:

Add this code in your view.

~~~
use bizley\partialpassword\widgets\PartialPassword;

echo $form->field($model, $attribute)->widget(PartialPassword::classname([
    'pattern' => $pattern,
    'bitsRange' => $bitsRange,
]));
~~~

## Behavior and widget options

For options description refer to the class' comments.

## Sample code

You can find samples of controller, models and views that might be helpful when implementing this behavior in your application in 'samples' folder.
You can also find migration file for 'password' table in 'migrations' folder.