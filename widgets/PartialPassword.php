<?php

/**
 * Partial password implementation for Yii 2.
 * @author Paweł Bizley Brzozowski
 * @version 1.0
 * @license http://opensource.org/licenses/Apache-2.0
 * 
 * https://github.com/bizley-code/yii2-partial-password
 */

namespace bizley\partialpassword\widgets;

use yii\base\InvalidParamException;
use yii\base\Widget;
use yii\helpers\Html;
use yii\web\View;

/**
 * PartialPassword widget generates the input fields for the password characters 
 * based on the given pattern.
 * Pattern is saved together with the partial password hash generated from the 
 * raw user's password.
 * 
 * (!) Notice that [[bitsRange]] value must be the same as set for the 
 * [[\bizley\partialpassword\behaviors\PartialPasswordBehavior]] that generated 
 * the partial passwords hashes.
 *
 * Basic usage:
 * ~~~
 * use bizley\partialpassword\widgets\PartialPassword;
 * echo $form->field($model, $attribute)->widget(PartialPassword::classname([
 *      'pattern' => $pattern,
 *      'bitsRange' => $bitsRange,
 * ]));
 * ~~~
 */
class PartialPassword extends Widget
{
    
    /**
     * @var string attribute associated with this widget.
     */
    public $attribute;
    
    /**
     * @var integer number of displayed fields.
     */
    public $bitsRange;
    
    /**
     * @var array input field options.
     * Options 'size' and 'maxlength' are set to 1.
     * Class [[PartialPassword::FIELD_CLASS]] is added.
     */
    public $inputOptions = ['style' => 'text-align:center'];
    
    /**
     * @var string custom client script code to be registered with widget 
     * instead of default one.
     * Set to false if you don't want any scripts.
     */
    public $jsCode;
    
    /**
     * @var integer registered script position as in [[View::registerJs()]].
     */
    public $jsPosition = View::POS_READY;
    
    /**
     * @var \yii\db\ActiveRecord model associated with this widget.
     */
    public $model;
    
    /**
     * @var integer active fields pattern.
     * Decimal representation of binary fields pattern.
     */
    public $pattern;
    
    /**
     * @var array widget templates.
     * Replacement strings:
     * - {positions}       = $template['positions'],
     * - {fields}          = $template['fields'],
     * - {single_position} = $template['single_position'],
     * - {number}          = position number,
     * - {single_field}    = $template['single_field'],
     * - {input}           = character input field.
     */
    public $template = [
        'whole'           => '<table>{positions}{fields}</table>',
        'positions'       => '<tr>{single_position}</tr>',
        'single_position' => '<td style="text-align:center">{number}</td>',
        'fields'          => '<tr>{single_field}</tr>',
        'single_field'    => '<td>{input}</td>',
        'empty_field'     => '<input type="text" value="" name="empty_password_field" disabled size="1">',
    ];
    
    /**
     * Name of the class added to every single character input field.
     * Default JavaScript in [[jsFocusNext()]] depends in this class.
     */
    const FIELD_CLASS  = 'partial-password-field';
    
    /**
     * Sets default options for the input field.
     * @return array
     */
    public function allInputOptions()
    {
        $options = is_array($this->inputOptions) ? $this->inputOptions : [];
        
        $options['size']      = 1;
        $options['maxlength'] = 1;
        $options['class']     = self::FIELD_CLASS . (isset($options['class']) ? ' ' . $options['class'] : '');
        
        return $options;
    }

    /**
     * Decodes fields pattern.
     * Pattern is saved as integer. To decode it it's translated to binary and 
     * mirrored. Every one is input field and every zero is blank field.
     * @return array
     */
    public function decodePattern()
    {
        return str_split(strrev((string)decbin($this->pattern)));
    }
    
    /**
     * Returns full length decoded pattern array.
     * @return array
     */
    public function getDecodedPattern()
    {
        $decodedPattern = array_fill(0, $this->bitsRange, 0);
        $pattern = $this->decodePattern();
        $size = count($pattern);
        for ($position = 0; $position < $size; $position++) {
            if ((int)$pattern[$position]) {
                $decodedPattern[$position] = 1;
            }
        }
        return $decodedPattern;
    }
    
    /**
     * Verifies widget configuration.
     * @throws InvalidParamException
     */
    public function init()
    {
        parent::init();
        
        if (!is_int($this->bitsRange) || $this->bitsRange < 0) {
            throw new InvalidParamException('Invalid bitsRange parameter. Required value must be integer greater than 0.');
        }
        if (!is_int($this->pattern) || $this->pattern < 0) {
            throw new InvalidParamException('Invalid pattern parameter. Required value must be integer greater than 0.');
        }
    }

    /**
     * Returns default JavaScript for the widget.
     * This focusing the next character input field after the previous has been 
     * filled (ignoring basic non-character buttons).
     * @return type
     */
    public function jsFocusNext()
    {
        return "jQuery(document).on('keyup', '." . self::FIELD_CLASS . "', function(e){ var ignored = [8, 9, 16, 17, 18, 20, 37, 38, 39, 40, 46]; if (ignored.indexOf(e.which) == -1) { var pp = jQuery('." . self::FIELD_CLASS . "'); var index = pp.index(this) + 1; if (index < pp.length) pp.eq(index).focus(); }});";
    }


    /**
     * Renders all input and blank fields.
     * @return string
     */
    public function renderEverySingleField()
    {
        $html = '';
        if (!empty($this->template['single_field'])) {
            foreach ($this->decodedPattern as $bit) {
                $html .= strtr($this->template['single_field'], ['{input}' => $this->renderInputField($bit)]);
            }
        }
        return $html;
    }
    
    /**
     * Renders all field numbers.
     * @return string
     */
    public function renderEverySinglePosition()
    {
        $html = '';
        if (!empty($this->template['single_position'])) {
            for ($bit = 1; $bit <= $this->bitsRange; $bit++) {
                $html .= strtr($this->template['single_position'], ['{number}' => $bit]);
            }
        }
        return $html;
    }
    
    /**
     * Renders fields part of widget.
     * @return string
     */
    public function renderFields()
    {
        return empty($this->template['fields']) ? null : strtr($this->template['fields'], [
            '{single_field}' => $this->renderEverySingleField(),
        ]);
    }
    
    /**
     * Renders single input or blank field.
     * @param integer $bit whether it is input field (1) or blank field (0).
     * @return string
     */
    public function renderInputField($bit)
    {
        if ($bit) {
            return Html::activePasswordInput($this->model, $this->attribute . '[]', $this->allInputOptions());
        }
        else {
            return !empty($this->template['empty_field']) ? $this->template['empty_field'] : null;
        }
    }
    
    /**
     * Renders positions part of widget.
     * @return string
     */
    public function renderPositions()
    {
        return empty($this->template['positions']) ? null : strtr($this->template['positions'], [
            '{single_position}' => $this->renderEverySinglePosition(),
        ]);
    }
    
    /**
     * Renders widget template.
     * @return string
     */
    public function renderTemplate()
    {
        return empty($this->template['whole']) ? null : strtr($this->template['whole'], [
            '{positions}' => $this->renderPositions(),
            '{fields}'    => $this->renderFields(),
        ]);
    }
    
    /**
     * Renders widget. Adds hidden field with selected pattern.
     * @return string
     */
    public function renderWidget()
    {
        $html = Html::activeHiddenInput($this->model, 'pattern', ['value' => $this->pattern]);
        $html .= $this->renderTemplate();
        return $html;
    }
    
    /**
     * Runs widget.
     * @return string
     */
    public function run()
    {
        if ($this->jsCode !== false) {
            $this->getView()->registerJs(empty($this->jsCode) ? $this->jsFocusNext() : $this->jsCode, !empty($this->jsPosition) ? $this->jsPosition : View::POS_READY);
        }
        
        return $this->renderWidget();
    }
}