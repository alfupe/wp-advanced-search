<?php
namespace WPAS;

class Input extends StdObject {

    private $input_name;
    private $id;
    private $class;
    private $attributes;
    private $label;
    private $field_type;
    private $format;
    private $placeholder;
    private $values;
    private $nested;
    private $selected;
    private $ctr;
    private $pre_html;
    private $post_html;
    private $disable_wrapper;

    protected static $rules = array(
                            'id' => 'string',
                            'attributes' => 'array<scalar>',
                            'field_type' => array('type' => 'FieldType',
                                                  'required' => true),
                            'label' => 'string',
                            'class' => 'array<string>',
                            'format' => array('type' => 'InputFormat',
                                              'required' => true),
                            'placeholder' => 'string|bool',
                            'values' => 'array',
                            'selected' => 'array',
                            'nested' => 'bool',
                            'allow_null' => 'bool|string',
                            'default_all' => 'bool',
                            'pre_html' => 'string',
                            'post_html' => 'string');

    private static $defaults = array(
                            'label' => '',
                            'placeholder' => false,
                            'values' => array(),
                            'selected' => array(),
                            'nested' => false,
                            'allow_null' => false,
                            'default_all' => false,
                            'disable_wrapper' => false,
                            'pre_html' => '',
                            'post_html' => '' );

    public function __construct($input_name, $args = array()) {
        $args = $this->parseArgs($args,self::$defaults);
        $args = $this->validate($input_name, $args, self::$defaults);
        $this->initMembers($input_name, $args);
    }

    /**
     * Validates the input_name and arguments
     *
     * @param string $input_name
     * @param array  $args
     * @throws \Exception
     * @throws \InvalidArgumentException
     * @return array
     */
    private function validate( $input_name, $args, $defaults ) {
        $validation = new Validator(self::$rules, $args, $defaults);
        if ($validation->fails()) {
            $errors = $validation->getErrors();
            $err_msg = $this->validationErrorMsg($errors);
            throw new \Exception($err_msg);
        }
        
        if (!is_string($input_name)) {
            $err_msg = $this->validationErrorMsg(
                array('Argument 1 `$field_name` ' .
                    'must be a string.'));
            throw new \InvalidArgumentException($err_msg);
        }

        return $validation->getArgs();
    }

    /**
     * Initializes object members
     *
     * @param string $input_name
     * @param array  $args
     */
    private function initMembers($input_name, $args) {
        $this->input_name = $input_name;

        foreach($args as $key => $value) {
            $this->$key = $value;
        }

        // For select fields, add null value if specified
        if ($this->format == 'select' && $this->allow_null && !empty($this->values)) {
            $null_val = ($this->allow_null === true) ? '' : $this->allow_null;
            $this->addNullOption($null_val);
        }

        if (!empty($this->class) && is_array($this->class)) {
            $this->class = implode(' ', $this->class);
        }

        $this->id = $this->input_name;
        $this->ctr = 1;
    }

    /**
     * Returns a string containing the full HTML content of the input, including
     * a wrapper div
     *
     * @return string
     */
    public function toHTML() {
        $output = '';

        if ($this->format != 'hidden') {

            $output .= $this->pre_html;

            if ($this->disable_wrapper == false) {
                $output .= '<div id="wpas-'.$this->id.'"  class="wpas-'
                    .$this->id.' wpas-'.$this->field_type.'-field wpas-field">';
            }

            if ($this->label) {
                $output .= '<div class="label-container">'.
                    '<label for="' .$this->id.'">'.$this->label.'</label></div>';
            }
        }

        $output .= $this->getInputInnerHTML();

        if ($this->format != 'hidden') {
            if ($this->disable_wrapper == false) {
                $output .= '</div>';
            }
            $output .= $this->post_html;
        }

        return $output;
    }

    /**
     * Returns a string containing the HTML content of the input
     *
     * @return string
     */
    private function getInputInnerHTML() {
        $format = $this->format;
        switch($format) {
            case 'multi-select':
                return $this->select(true);
                break;
            case 'text':
                return $this->input();
                break;
            case 'color':
            case 'date':
            case 'datetime':
            case 'datetime-local':
            case 'email':
            case 'month':
            case 'number':
            case 'range':
            case 'search':
            case 'tel':
            case 'time':
            case 'url':
            case 'week':
                return $this->input( $this->format );
                break;
            default :
                return $this->$format();
        }
    }

    /**
     * Generates a select field
     */
    private function select($multi = false) {

        $output = '<select id="'.$this->id.'" name="'.$this->input_name;
        if ($multi) {
            $output .= '[]';
        }

        $output .=  '"';
        $output .= ($multi) ? ' multiple="multiple"' : '';
        $output .= '  class="';
        $output .= ($multi) ? 'wpas-multi-select' : 'wpas-select';
        $output .= ' ' . $this->class.'"';
        $output .= $this->attributesString();
        $output .= '>';

        if ($this->nested) {
            $output .= $this->buildOptionsList($this->values,
                                             array($this, 'selectOption'), 0);
        } else {
            foreach ($this->values as $value => $label) {
                $output .= $this->selectOption($value, $label);
            }
        }

        $output .= '</select>';
        return $output;
    }

    /**
     * Generates a checkbox field
     *
     * @since 1.0
     */
    private function checkbox() {
        return $this->listField(true);
    }

    /**
     * Generates a radio field
     *
     * @since 1.0
     */
    private function radio() {
        return $this->listField(false);
    }

    /**
     * Generates a list-style field (either checkboxes or radio buttons)
     *
     * @param bool $is_checkbox
     * @return string
     */
    private function listField($is_checkbox = true) {
        $group_label = ($is_checkbox) ? 'checkboxes' : 'radio-buttons';
        $option_func = ($is_checkbox) ? 'checkboxOption' : 'radioOption';

        $output = '<div class="wpas-'.$this->id.'-'.$group_label.' wpas-'.$group_label.' field-container">';

        if ($this->nested) {
            $output .= $this->buildOptionsList($this->values, array($this, $option_func), 0, '<ul>', '</ul>');
            return $output;
        }

        foreach ($this->values as $value => $label) {
            $output .= call_user_func(array($this,$option_func), $value, $label);
        }
        $output .= '</div>';
        return $output;
    }

    /**
     * Generates a text input field
     *
     * Also used to generate other HTML5 field types through use of $input_type
     * argument.
     *
     * @since 1.0
     */
    private function input( $input_type = 'text' ) {
        $value = $this->getInputValue();
        $placeholder = '';
        if ($this->placeholder)
            $placeholder = ' placeholder="'.$this->placeholder.'"';
        $output = '<input type="'.$input_type.'" id="'.$this->id.'" class="wpas-'.$input_type.' '.$this->class.'" value="'.$value.'" name="'.$this->input_name.'"'.$placeholder.' '.$this->attributesString().'>';
        return $output;
    }

    /**
     * Generates a textarea field
     *
     * @since 1.0
     */
    private function textarea() {
        $value = $this->getInputValue();
        $placeholder = '';
        if ($this->placeholder)
            $placeholder = ' placeholder="'.$this->placeholder.'"';
        $output = '<textarea id="'.$this->id.'" class="wpas-textarea '.$this->class.'" name="'.$this->input_name.'"'.$placeholder.'  '.$this->attributesString().'>'.$value.'</textarea>';
        return $output;
    }

    /**
     * Generates a submit button
     *
     * @since 1.0
     */
    private function submit() {
        $value = reset($this->values);
        $output = '<input type="submit" class="wpas-submit '.$this->class.'" value="'.$value.'" '.$this->attributesString().'>';
        return $output;
    }

    /**
     * Generates a reset button
     *
     * @since 1.4
     */
    private function reset() {
        $value = reset($this->values);
        $output = '<input type="reset" class="wpas-reset '.$this->class.'" value="'.$value.'" '.$this->attributesString().'>';
        return $output;
    }

    /**
     * Generates a clear button
     *
     * @since 1.4
     */
    private function clear() {
        $value = reset($this->values);
        $output = '<button class="wpas-clear '.$this->class.'" '.$this->attributesString().'>'.$value.'</button>';
        return $output;
    }

    /**
     * Generates an html field
     *
     * @since 1.0
     */
    private function html() {
        return reset($this->values);
    }

    /**
     * Generates a hidden field
     *
     * @since 1.0
     */
    private function hidden() {
        $value = reset($this->values);
        $output = '<input type="hidden" name="'.$this->input_name.'" value="'.$value.'" '.$this->attributesString().'>';
        return $output;
    }

    /**
     *  Builds and returns list of field options
     *
     *  Used for select, checkbox, and radio fields.  Supports nested
     *  hierarchies of elements.
     */
    private function buildOptionsList($elements = array(), $field_func,
                                            $level = 0, $pre = '', $post = '') {
        if (empty($elements)) return "";

        $output = "";
        $output .= $pre;

        foreach($elements as $element) {
            $output .= call_user_func($field_func, $element['value'],
                                                    $element['label'], $level);
            $output .= $this->buildOptionsList($element['children'],
                                               $field_func, $level+1, $pre,
                                               $post);
        }

        $output .= $post;

        return $output;
    }

    /**
     * Creates a string of HTML element attributes for the input
     */
    private function attributesString() {
        $output = "";
        if ($this->attributes) {
            foreach($this->attributes as $k => $v) {
                $output .= $k . '="'.$v.'" ';
            }
        }
        return $output;
    }

    /**
     * Generates a single option for a select field
     *
     * @since 1.3
     */
    private function selectOption($value, $label, $level = 0) {
        $indent = '';
        if ($level > 0) {
            for($i=0; $i<$level; $i++) {
                $indent .= "—";
            }
            $indent .= ' ';
        }
        $output = '<option value="'.$value.'"';
        if (in_array($value, $this->selected)) {
            $output .= ' selected="selected"';
        }
        $output .= '>'.$indent.$label.'</option>';
        return $output;
    }

    /**
     * Generates a single option for a checkbox field
     *
     * @since 1.3
     */
    private function checkboxOption($value, $label, $level = 0) {
        return $this->listOption($value, $label, 'checkbox');
    }

    /**
     * Generates a single option for a radio field
     *
     * @since 1.3
     */
    private function radioOption($value, $label, $level = 0) {
        return $this->listOption($value, $label, 'radio');
    }

    /**
     * Generates a single field option for a checkbox or radio field
     *
     * @param $value
     * @param $label
     * @param int $level
     * @param string $type "checkbox" or "radio"
     * @return string
     */
    private function listOption($value, $label, $type = 'checkbox') {
        $ctr = $this->ctr;
        $el = ($this->nested) ? 'li' : 'div';
        $output = '';
        $output .= '<'.$el.' class="wpas-'.$this->id.'-'.$type.'-'.$ctr.'-container wpas-'.$this->id.'-'.$type.'-container wpas-'.$type.'-container">';
        $output .= '<input type="'.$type.'" id="wpas-'.$this->id.'-'.$type.'-'.$ctr.'" class="wpas-'.$this->id.'-'.$type.' wpas-'.$type.' '.$this->class.'" name="'.$this->input_name.'[]" value="'.$value.'"';
        if (in_array($value, $this->selected, true)) {
            $output .= ' checked="checked"';
        }
        $output .= '>';
        $output .= '<label for="wpas-'.$this->id.'-'.$type.'-'.$ctr.'"> '.$label.'</label></'.$el.'>';
        $this->ctr++;
        return $output;
    }

    /**
     * Obtains the value to use in the field.
     *
     * Used only for text & textarea inputs
     *
     * @since 1.3
     */
    private function getInputValue() {
        $value = '';

        if (!empty($this->selected)) {
            $value = reset($this->selected);
        } else if (!empty($this->values)) {
            $value = reset($this->values);
        }
        return $value;
    }

    /**
     * For select fields, adds a null option to the beginning of the menu
     *
     * @since 1.3
     */
    private function addNullOption( $null_label ) {
        if ($this->nested) {
            $null_option = array(
                'value' => '',
                'label' => $null_label,
                'children' => array()
            );
        } else {
            $null_option = $null_label;
        }

        $arr = array_reverse($this->values, true);
        $arr[''] = $null_option;
        $arr = array_reverse($arr, true);
        $this->values = $arr;
    }

    public function disableWrapper() {
        $this->disable_wrapper = true;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getInputName()
    {
        return $this->input_name;
    }

    /**
     * @return mixed
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @return mixed
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @return mixed
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return mixed
     */
    public function getFieldType()
    {
        return $this->field_type;
    }

    /**
     * @return mixed
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @return mixed
     */
    public function getPlaceholder()
    {
        return $this->placeholder;
    }

    /**
     * @return mixed
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @return bool
     */
    public function isNested()
    {
        return $this->nested;
    }

    /**
     * @return mixed
     */
    public function getSelected()
    {
        return $this->selected;
    }

    /**
     * @return mixed
     */
    public function getPreHtml()
    {
        return $this->pre_html;
    }

    /**
     * @return mixed
     */
    public function getPostHtml()
    {
        return $this->post_html;
    }

}