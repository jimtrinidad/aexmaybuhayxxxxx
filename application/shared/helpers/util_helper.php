<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

function microsecID() {
	$v = round(microtime(true) * 1000);
    // just returning $v as floats converts to exponential value
    return number_format($v, 0, '', '');
}

function generate_public_id($lastname, $suffix_length = 4)
{
    // remove all except letters
    $clean  = preg_replace("/[^A-Za-z]/", '', $lastname);
    $name   = strtoupper($clean);

    $randomNumber = random_number($suffix_length);

    $id = $name . $randomNumber;

    // check if not exists
    $ci =& get_instance();
    $query = $ci->db->where('PublicID', $id)->get('Users');
    if ($query->num_rows() > 0) {
        // retry if exists
        $id = generate_public_id($lastname);
    }

    return $id;
}

function datetime() {
    return date('Y-m-d H:i:s');
}

function current_controller()
{
    $ci =& get_instance();
    return $ci->router->fetch_class();
}

function current_method()
{
    $ci =& get_instance();
    return $ci->router->fetch_method();
}

function is_current_url($controller, $method = false)
{
    if (current_controller() != $controller) {
        return false;
    }

    if ($method && current_method() != $method) {
        return false;
    }

    return true;
}

function recache() {
    return time();
}


function random_number($length)
{
    return join('', array_map(function($value) { return mt_rand(0, 9); }, range(1, $length)));
}


function random_password($length = 8) 
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    $password = array(); 
    $alpha_length = strlen($alphabet) - 1; 
    for ($i = 0; $i < $length; $i++) 
    {
        $n = rand(0, $alpha_length);
        $password[] = $alphabet[$n];
    }
    return implode($password); 
}

function random_letters($length = 8) 
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $letters  = array(); 
    $alpha_length = strlen($alphabet) - 1; 
    for ($i = 0; $i < $length; $i++) 
    {
        $n = rand(0, $alpha_length);
        $letters[] = $alphabet[$n];
    }
    return implode($letters); 
}


function account_public_id()
{
    if (current_user()) {
        return '09' . str_pad(current_user(), 4, '0', STR_PAD_LEFT);
    }
    return false;
}

/**
* get qr file
* generate new if not exists
*/
function get_qr_file($data, $size = 3)
{
    $extension  = 'png';
    $key        = md5($data);
    $filename   = $key . '.' . $extension;
    $qr_path    = PUBLIC_DIRECTORY . 'assets/qr/' . $filename;
    if (file_exists($qr_path)) {
        return $filename;
    } else {
        // generate new
        $ci =& get_instance();
        $ci->load->library('qr/ciqrcode', array(
            'cachedir'  => APPPATH . 'cache/',
            'errorlog'  => APPPATH . 'logs/'
        ));

        $qrparams['data']   = $data;
        $qrparams['level']  = 'H';
        $qrparams['size']   = $size;
        $qrparams['black']  = array(13, 54, 17);
        $qrparams['savename'] = $qr_path;
        $ci->ciqrcode->generate($qrparams);
        if (file_exists($qr_path)) {
            return $filename;
        }
    }
    return false;
}

/**
* image to data uri
*/
function getDataURI($image, $mime = '') {
    return 'data: '.(function_exists('mime_content_type') ? mime_content_type($image) : $mime).';base64,'.base64_encode(file_get_contents($image));
}

/**
* quick print r with pre and exit;
*/
function print_data($data, $exit = false)
{
    echo '<pre>';
    print_r($data);
    echo '</pre>';
    if ($exit) {
        exit;
    }
}


/**
* time ago
* get time difference
*/
function time_ago($from, $until = 'now', $format = 'string')
{
    $date = new DateTime($from);
    $interval = $date->diff(new DateTime($until));

    $diff = array(
            'y' => $interval->y,
            'm' => $interval->m,
            'd' => $interval->d,
            'h' => $interval->h,
            'i' => $interval->i,
            's' => $interval->s,
        );

    if ($format == 'array') {
        return $diff;
    } else {
        $str = '';
        if ($diff['y']) {
            $str .= '%yy, ';
        }
        if ($diff['m']) {
            $str .= '%mm, ';
        }
        if ($diff['d']) {
            $str .= '%dd, ';
        }
        if ($diff['h']) {
            $str .= '%hh, ';
        }
        $str .= '%imin';

        return $interval->format($str);
    }
}


/**
* number to words
*/
function number_to_words($number)
{
    $f = new NumberFormatter("en_US", NumberFormatter::SPELLOUT);
    $f->setTextAttribute(NumberFormatter::DEFAULT_RULESET, "%spellout-numbering-verbose");
    return $f->format($number);
}

function peso($number)
{
    return '₱' . number_format($number);
}


function csrf_token_input_field()
{
    $ci =& get_instance();
    return '<input type="hidden" name="' . $ci->security->get_csrf_token_name() . '" value="' . $ci->security->get_csrf_hash() . '">';
}



/**
* commision distribution
* @param item price
* @param commision value (percent or actual profit)
* @param commision type (1 - transaction fee, 2 - commision percent)
*
* type 1 - get profit first (refer to excel for compuration)
* type 2 - commission is the profit
*/
function profit_distribution($srp, $commision, $type)
{
    if ($type == 1) {
        $profit = $commision;
    } else {
        // get profit
        $supplier_price     = $srp - ($srp * ($commision/100));
        $discount_rate      = partner_commision_rate($commision);
        $discount           = ($discount_rate > 0 ? ($srp * ($discount_rate/100)) : 0);
        $discounted_price   = $srp - $discount;
        $profit             = $discounted_price - $supplier_price;
    }

    $data = array(
        'company'        => $profit * 0.30,
        'investor'       => $profit * 0.25,
        'referral'       => $profit * 0.30,
        'delivery'       => $profit * 0.05,
        'cashback'       => $profit * 0.02,
        'shared_rewards' => $profit * 0.08,
    );

    $data['divided_reward'] = ($data['shared_rewards'] > 0 ? ($data['shared_rewards'] / 8) : 0);

    return $data;
}

/**
* return the exact commision percent
*/
function partner_commision_rate($c)
{
    if ($c >= 1 && $c <= 8) {
        return 1;
    } else if ($c >= 9 && $c <= 17) {
        return 3;
    } else if ($c >= 18 && $c <= 26) {
        return 6;
    } else if ($c >= 27 && $c <= 35) {
        return 9;
    } else if ($c >= 36 && $c <= 44) {
        return 12;
    } else if ($c >= 45 && $c <= 53) {
        return 15;
    } else if ($c >= 54 && $c <= 62) {
        return 18;
    } else if ($c >= 63 && $c <= 71) {
        return 21;
    } else if ($c >= 72 && $c <= 80) {
        return 24;
    } else if ($c >= 80 && $c <= 100) {
        return 27;
    }

    return 0;
}