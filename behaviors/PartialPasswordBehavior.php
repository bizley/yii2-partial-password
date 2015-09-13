<?php

/**
 * Partial password implementation for Yii 2.
 * @author Paweł Bizley Brzozowski
 * @version 1.0
 * @license http://opensource.org/licenses/Apache-2.0
 * 
 * https://github.com/bizley-code/yii2-partial-password
 */

namespace bizley\partialpassword\behaviors;

use Yii;
use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\db\Expression;
use yii\db\Query;

/**
 * PartialPasswordBehavior generates the partial password hashes based on raw 
 * user's password that can be used in login process - instead of typing the 
 * whole password user has to type only few selected characters from it.
 * This code generated random number of partial passwords hashes with random 
 * patterns of characters to type.
 * 
 * Use [[\bizley\partialpassword\widgets\PartialPassword]] widget to render the 
 * characters fields based on the selected pattern.
 * 
 * You can find sample of controller, models and views that might be helpful 
 * when implementing this behavior in your application in 'samples' folder.
 * You can find migration file for 'password' table in 'migrations' folder.
 *
 * Usage:
 * ~~~
 * use bizley\partialpassword\behaviors\PartialPasswordBehavior;
 *
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => PartialPasswordBehavior::className(),
 *             'bitsRange' => 20,
 *             'passwordsMin' => 5,
 *             'passwordsMax' => 8,
 *             'charactersMin' => 3,
 *             'charactersMax' => 5,
 *             'repeatDropRate' => PartialPasswordBehavior::REPEAT_DROP_SMALL,
 *             'tableName' => '{{%password}}',
 *         ],
 *     ];
 * }
 * ~~~
 * 
 * =============================================================================
 * Important methods:
 * =============================================================================
 * 
 * - [[generatePartialHashes()]]       - generates partial password hashes.
 * - [[savePartialHashes()]]           - saves hashes in database (generates 
 *                                       them first if not generated). Also 
 *                                       optionally deletes previously saved 
 *                                       hashes.
 * - [[deletePreviousPartialHashes()]] - deletes previously saved hashes.
 * - [[getPartialHashForPattern()]]    - selects the partial password hash for 
 *                                       the given pattern from database.
 * - [[getRandomPartialPattern()]]     - selects a random pattern from database.
 * - [[validatePartialPassword()]]     - validates user partial password.
 * 
 * =============================================================================
 * 
 * (!) Note that the biggest generated pattern for active password fields cannot 
 * be greater than PHP_INT_MAX value of your environment. This is the 
 * problem only in case of really large values.
 * For example, with bitsRange = 20 and charactersMax = 20 the biggest 
 * pattern is 1048575. For 40 and 40 it's 1099511627775.
 * 
 * (!!) Make sure that time required for generating all the passwords is not 
 * time-outing your application and also that it's not tedious for users.
 * In case of generating many passwords it might be better to move the process 
 * outside the normal registration flow.
 */
class PartialPasswordBehavior extends Behavior
{
    
    /**
     * @var integer maximum number of characters in password.
     * Users' passwords longer than $bitsRange will be silently trimmed to this 
     * value so for example if bitsRange = 5 and user is registering with 
     * password 'mySamplePassword2001' the partial passwords will be generated 
     * from 'mySam' only. You can add validating rule for the password attribute 
     * that limits the raw password length if you want.
     * This value cannot be smaller than 1 (but what's the point of 1-character 
     * password?).
     */
    public $bitsRange;
    
    /**
     * @var integer maximum number of password characters the user can be asked 
     * to typed when signing in.
     * This value cannot be greater than [[$bitsRange]] and smaller than 1.
     */
    public $charactersMax;
    
    /**
     * @var integer minimum number of password characters the user can be asked 
     * to typed when signing in.
     * This value cannot be greater than [[$charactersMax]] and smaller than 1.
     * (!) Notice that when this value is small system can generate short 
     * passwords which is potentially not secure.
     */
    public $charactersMin;
    
    /**
     * @var string password's characters encoding.
     * Default is 'UTF-8'.
     */
    public $encoding = 'UTF-8';
    
    /**
     * @var integer maximum number of generated password hashes for the user.
     * This value cannot be smaller than 1 but in case of only one password 
     * system always asks for the same characters pattern.
     * Greater number generates larger variety of characters patterns for the 
     * user but at the same time produces the larger database storage size and 
     * longer generating time.
     */
    public $passwordsMax;
    
    /**
     * @var integer minimum potential number of generated password hashes for 
     * the user.
     * This value cannot be greater than [[$passwordsMax]] and smaller than 1.
     * (!) It is possible that the actual number of generated partial passwords 
     * hashes will be smaller than this value - it depends on given parameters.
     */
    public $passwordsMin;
    
    /**
     * @var integer repeat drop rate flag for the characters position.
     * Available values are:
     *  self::REPEAT_DROP_BIG (3), repeating position rate drops by 50,
     *  self::REPEAT_DROP_MEDIUM (2), repeating position rate drops by 25,
     *  self::REPEAT_DROP_SMALL (1), repeating position rate drops by 10.
     *  self::REPEAT_DROP_TINY (0), repeating position rate drops by 1,
     * Default value is self::REPEAT_DROP_SMALL (1).
     * Higher repeat drop rate means that computing time is shorter but it is 
     * more likely to generate less partial password hashes than required.
     */
    public $repeatDropRate = self::REPEAT_DROP_SMALL;
    
    /**
     * @var string database table for passwords hashes.
     */
    public $tableName = '{{%password}}';
    
    /**
     * @var array generated partial password hashes.
     */
    protected $_hashes;
    
    /**
     * @var array positions with their repeat drop values.
     */
    protected $_indexes;
    
    /**
     * @var string raw trimmed password.
     */
    protected $_raw_password;
    
    /**
     * @var array raw trimmed password array.
     */
    protected $_raw_password_array;
    
    /**
     * Repeat drop rate constants.
     */
    const REPEAT_DROP_BIG    = 3;
    const REPEAT_DROP_MEDIUM = 2;
    const REPEAT_DROP_SMALL  = 1;
    const REPEAT_DROP_TINY   = 0;
    
    /**
     * Returns password characters pattern.
     * This is decimary representation of binary string where 1s are positions 
     * of characters user will be asked for and 0s are blank fields.
     * @param array $indexes
     * @return integer
     */
    protected function _createPattern($indexes)
    {
        $positions = array_fill(0, $this->bitsRange, 0);
        foreach ($indexes as $index) {
            $positions[$index] = 1;
        }
        return bindec(strrev(implode('', $positions)));
    }

    /**
     * Returns list of characters from password for the password hash.
     * Characters are cut based on their position in password.
     * @param array $indexes
     * @return string
     */
    protected function _cutCharacters($indexes)
    {
        $characters = '';
        $passwordArray = $this->getRawPasswordArray();
        foreach ($indexes as $index) {
            $characters .= isset($passwordArray[$index]) ? $passwordArray[$index] : '';
        }
        return $characters;
    }
    
    /**
     * Generates unique partial password hash for the user based on random 
     * pattern and adds it to the list.
     */
    protected function _generatePartialHash()
    {
        do {
            $charactersIndexes = $this->_generateRandomCharactersIndexes();
            if (!empty($charactersIndexes)) {
                $pattern = $this->_createPattern($charactersIndexes);
            }
            else {
                return;
            }
        } while (isset($this->getPartialHashes()[$pattern]));
        
        $this->addPartialHash($pattern, Yii::$app->security->generatePasswordHash($this->_cutCharacters($charactersIndexes)));
    }
    
    /**
     * Returns array with random positions based on given parameters.
     * @return array
     */
    protected function _generateRandomCharactersIndexes()
    {
        $indexes = [];
        
        $possibleValues = $this->getRemainingPositions();
        $max = min([$this->charactersMax, count($possibleValues)]);
        if ($max >= $this->charactersMin) {
            $charactersNumber = $this->generateRandomNumber($this->charactersMin, $max);
            while ($charactersNumber) {
                $count = count($possibleValues);
                if ($count) {
                    $chosenIndex = $this->generateRandomNumber(0, $count - 1);
                    $this->reduceRate($possibleValues[$chosenIndex]);
                    $cut = array_splice($possibleValues, $chosenIndex, 1);
                    $indexes[] = $cut[0];
                }
                $charactersNumber--;
            }
            sort($indexes);
        }
        
        return $indexes;
    }
    
    /**
     * Stores partial password hash.
     * Array key is hash pattern and value is hash itself.
     * @param integer $pattern
     * @param string $hash
     */
    public function addPartialHash($pattern, $hash)
    {
        $this->_hashes[$pattern] = $hash;
    }
    
    /**
     * Deletes all previously created user's partial password hashes from 
     * database.
     * @param integer $user_id
     * @throws \yii\db\Exception
     */
    public function deletePreviousPartialHashes($user_id = null)
    {
        Yii::$app->db->createCommand()->delete($this->tableName, ['user_id' => $user_id])->execute();
    }

    /**
     * Prepares the array of hashes to be batch-inserted to database.
     * @param integer $user_id
     * @return array
     */
    public function formatHashesForDb($user_id = null)
    {
        $formatted = [];
        
        foreach ($this->getPartialHashes() as $pattern => $password_hash) {
            $formatted[] = [
                $user_id,
                $pattern,
                $password_hash
            ];
        }
        
        return $formatted;
    }

    /**
     * Generates partial password hashes from raw password.
     * @param string $password
     */
    public function generatePartialHashes($password)
    {
        $this->setRawPassword($password);
        $this->setIndexes();
        
        $hashesNumber = $this->generateRandomNumber($this->passwordsMin, $this->passwordsMax);
        for ($hash = 0; $hash < $hashesNumber; $hash++) {
            $this->_generatePartialHash();
        }
    }

    /**
     * Generates random integer between two values inclusive.
     * @param integer $min
     * @param integer $max
     * @return integer
     */
    public function generateRandomNumber($min, $max)
    {
        return mt_rand($min, $max);
    }
    
    /**
     * Returns user's partial password hash for the given pattern.
     * @param integer $pattern
     * @return array
     * @throws \yii\db\Exception
     */
    public function getPartialHashForPattern($pattern)
    {
        return (new Query)->from($this->tableName)->select(['password_hash'])->where(['user_id' => $this->owner->id, 'pattern' => $pattern])->limit(1)->one();
    }

    /**
     * Returns list of generated partial password hashes with patterns.
     * @return array
     */
    public function getPartialHashes()
    {
        return $this->_hashes;
    }
    
    /**
     * Returns user's partial password hash pattern.
     * @return array
     * @throws \yii\db\Exception
     */
    public function getRandomPartialPattern()
    {
        return (new Query)->from($this->tableName)->select(['pattern'])->where(['user_id' => $this->owner->id])->orderBy(new Expression('RAND()'))->limit(1)->one();
    }
    
    /**
     * Returns repeat drop rate value.
     * @return integer
     */
    public function getRepeatDropValue()
    {
        switch ($this->repeatDropRate) {
            case self::REPEAT_DROP_BIG:
                return 50;
            
            case self::REPEAT_DROP_MEDIUM:
                return 25;
            
            case self::REPEAT_DROP_SMALL:
                return 10;
                
            case self::REPEAT_DROP_TINY:
                return 1;
        }
    }
    
    /**
     * Returns list of positions with their repeat drop values.
     * @return array
     */
    public function getIndexes()
    {
        return $this->_indexes;
    }
    
    /**
     * Returns maximum possible integer number for the environment.
     * @return integer
     */
    public function getMaximumInt()
    {
        return \PHP_INT_MAX;
    }
    
    /**
     * Returns raw trimmed password.
     * @return string
     */
    public function getRawPassword()
    {
        return $this->_raw_password;
    }
    
    /**
     * Returns array made of each character of raw trimmed password.
     * @return array
     */
    public function getRawPasswordArray()
    {
        return $this->_raw_password_array;
    }
    
    /**
     * Returns list of all not excluded positions that can be selected to create 
     * pattern.
     * Position is excluded when its repeat drop value is 0 or less.
     * @return array
     */
    public function getRemainingPositions()
    {
        $remaining = [];
        
        foreach ($this->_indexes as $position => $rate) {
            if ($rate > 0) {
                $remaining[] = $position;
            }
        }
        
        return $remaining;
    }

    /**
     * Verifies behavior configuration.
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();
        
        if (!is_int($this->bitsRange) || $this->bitsRange < 1) {
            throw new InvalidConfigException('Invalid PartialPasswordBehavior bitsRange parameter. Required value must be integer greater than 0.');
        }
        if (!is_int($this->charactersMax) || $this->charactersMax < 1 || $this->charactersMax > $this->bitsRange) {
            throw new InvalidConfigException('Invalid PartialPasswordBehavior charactersMax parameter. Required value must be integer greater than 0 and not greater than bitsRange parameter.');
        }
        if (!is_int($this->charactersMin) || $this->charactersMin < 1 || $this->charactersMin > $this->charactersMax) {
            throw new InvalidConfigException('Invalid PartialPasswordBehavior charactersMin parameter. Required value must be integer greater than 0 and not greater than charactersMax parameter.');
        }
        if (!is_int($this->passwordsMax) || $this->passwordsMax < 1) {
            throw new InvalidConfigException('Invalid PartialPasswordBehavior passwordsMax parameter. Required value must be integer greater than 0.');
        }
        if (!is_int($this->passwordsMin) || $this->passwordsMin < 1 || $this->passwordsMin > $this->passwordsMax) {
            throw new InvalidConfigException('Invalid PartialPasswordBehavior passwordsMin parameter. Required value must be integer greater than 0 and not greater than passwordsMax parameter.');
        }
        if (!in_array($this->repeatDropRate, $this->listDropRates())) {
            throw new InvalidConfigException('Invalid PartialPasswordBehavior repeatDropRate parameter. Required value must be 0, 1, 2 or 3.');
        }
    }
    
    /**
     * Returns list of all repeat drop rates.
     * @return array
     */
    public function listDropRates()
    {
        return [
            self::REPEAT_DROP_BIG,
            self::REPEAT_DROP_MEDIUM,
            self::REPEAT_DROP_SMALL,
            self::REPEAT_DROP_TINY,
        ];
    }
    
    /**
     * Reduces the repeat drop value of selected position.
     * @param integer $index
     */
    public function reduceRate($index)
    {
        if (isset($this->_indexes[$index])) {
            $this->_indexes[$index] -= $this->getRepeatDropValue();
        }
    }

    /**
     * Saves the generated user's partial password hashes in database.
     * @param string $password raw user password, if not given owner password is taken
     * @param integer $user_id user id, of not given owner id is taken
     * @param boolean $deletePrevious wheter previously generated hashes should be deleted from database first
     * @return integer number of affected database rows
     * @throws InvalidParamException
     * @throws \yii\db\Exception
     */
    public function savePartialHashes($password = null, $user_id = null, $deletePrevious = true)
    {
        if (empty($user_id)) {
            $user_id = $this->owner->id;
        }
        if (!empty($user_id)) {
            
            if ($deletePrevious) {
                $this->deletePreviousPartialHashes($user_id);
            }
            
            if (empty($this->_hashes)) {
                if (empty($password)) {
                    $password = $this->owner->password;
                }
                if (!empty($password)) {
                    $this->generatePartialHashes($password);
                }
                else {
                    throw new InvalidParamException('No password given.');
                }
            }
            
            return Yii::$app->db->createCommand()->batchInsert($this->tableName, ['user_id', 'pattern', 'password_hash'], $this->formatHashesForDb($user_id))->execute();
        }
        else {
            throw new InvalidParamException('No user_id given.');
        }        
    }

    /**
     * Generates range of positions with the starting repeat drop value.
     * @return array
     */
    public function setIndexes()
    {
        $this->_indexes = array_fill(0, $this->bitsRange, 100);
    }
    
    /**
     * Sets raw password by trimming it to maximum allowed length and sets array 
     * of raw password by splitting each character of the password.
     * @param string $password user-typed password
     */
    public function setRawPassword($password)
    {
        $this->_raw_password = mb_substr($password, 0, $this->bitsRange, $this->encoding);
        $this->_raw_password_array = str_split($this->_raw_password);
    }
    
    /**
     * Tests generator with the given conditions.
     * @param string $password
     * @param integer $bitsRange
     * @param integer $passwordsMin
     * @param integer $passwordsMax
     * @param integer $charactersMin
     * @param integer $charactersMax
     * @param integer $repeatDropRate
     * @param string $encoding
     * @return array
     */
    public function testGenerator($password, $bitsRange, $passwordsMin, $passwordsMax, $charactersMin, $charactersMax, $repeatDropRate = self::REPEAT_DROP_SMALL, $encoding = 'UTF-8')
    {
        $this->bitsRange = $bitsRange;
        $this->passwordsMin = $passwordsMin;
        $this->passwordsMax = $passwordsMax;
        $this->charactersMin = $charactersMin;
        $this->charactersMax = $charactersMax;
        $this->repeatDropRate = $repeatDropRate;
        $this->encoding = $encoding;
        
        $this->init();
        
        $timeStart = microtime(true);
        $this->generatePartialHashes($password);
        $timeStop = microtime(true) - $timeStart;
        
        return [
            'Conditions' => [
                'password' => $password,
                'bitsRange' => $this->bitsRange, 
                'passwordsMin' => $this->passwordsMin,
                'passwordsMax' => $this->passwordsMax, 
                'charactersMin' => $this->charactersMin,
                'charactersMax' => $this->charactersMax,
                'repeatDropRate' => $this->repeatDropRate,
                'encoding' => $this->encoding
            ],
            'Trimmed password' => $this->getRawPassword(),
            'Final position rates' => $this->getIndexes(),
            'Remaining positions' => $this->getRemainingPositions(),
            'Generated in' => $timeStop . 's',
            'Hashes' => $this->getPartialHashes(),
        ];
    }
    
    /**
     * Validates the user's partial password.
     * @param integer $pattern
     * @param string $password
     * @return boolean
     */
    public function validatePartialPassword($pattern, $password)
    {
        $hash = $this->getPartialHashForPattern($pattern);
        return Yii::$app->security->validatePassword($password, isset($hash['password_hash']) ? $hash['password_hash'] : null);
    }
}