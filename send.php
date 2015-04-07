<?php

require_once("parameters/parameters.php");

function read_stdin($prompt, $valid_inputs, $default = '') {
    while(!isset($input)
        || (is_array($valid_inputs) && !in_array($input, $valid_inputs))
        || ($valid_inputs == 'is_file' && !is_file($input))) {
        echo $prompt;
        $input = trim(fgets(STDIN));
        if(empty($input) && !empty($default)) {
            $input = $default;
        }
    }

    return $input;
}

function read_in_csv() {
    // check input is valid file name, use /var/path for input nothing
    // ini_set("auto_detect_line_endings", "1");
    return read_stdin('Please input the csv file (/path/to/numbers.csv): ', 'is_file', 'numbers.csv');
}

function parse_csv($file_name) {
        ini_set('auto_detect_line_endings',TRUE);
        $file = fopen($file_name, 'r');
        $return = array();
        while (($line = fgetcsv($file)) !== FALSE) {
            $return = array_merge($return, $line);
        }
        fclose($file);
        ini_set('auto_detect_line_endings',FALSE);

        return $return;
}

// Actually send the SMS, via Twilio
function send_sms($message, $numbers) {
    $post = NULL;

    $sid = SID;
    $auth = AUTH;
    $from = FROM_NUMBER;
    $version = API_VERSION;
    $url = 'https://api.twilio.com/'.$version.'/Accounts/'.$sid.'/Messages.json';
    $sent_count = 0;
    foreach ($numbers as $number) {
        $data = array(
            'To' => urlencode($number),
            'From' => urlencode($from),
            'Body' => urlencode($message),
        );

        $fields = '';
        foreach($data as $key => $value) { 
            $fields .= $key . '=' . $value . '&'; 
        }
        rtrim($fields, '&');

        $post = curl_init();

        curl_setopt($post, CURLOPT_URL, $url);
        curl_setopt($post, CURLOPT_POST, count($data));
        curl_setopt($post, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($post, CURLOPT_USERPWD, $sid.':'.$auth);
        curl_setopt($post, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($post);

        curl_close($post);

        if ($result) {
            fwrite(STDOUT, "Message sent to number: ".$number."\n");
            $sent_count++;
        } else {
            fwrite(STDOUT, "Error. Something went wrong here. (".$number.")\n");
        }
    }

    return $sent_count;
}

// Get and send message to number(s)
function send_message($numbers) {
    $message = read_stdin('Message to send: ', '');
    $confirmation = read_stdin('Send message "'.$message.'" to '.count($numbers).' number(s)? (Y/N): ', array('Y', 'y', 'N', 'n'));

    switch ($confirmation) {
        case 'Y':
        case 'y':
            $send_result = send_sms($message, $numbers);
            fwrite(STDOUT, "Done! Message sent to ".$send_result." number(s).\n");
            break;
        case 'N':
        case 'n':
            fwrite(STDOUT, "Ok. Not sending after all.\n");
            break;
    }
}

// Leave only numbers in number string
function clean_number($number) {
    return trim(preg_replace('/[^0-9]/','',$number));
}

// Removing anything that isn't a properly formatted phone number
function real_phone_number($number) {
    $return = TRUE;
    if (!intval($number) || strlen($number) < 10 || strlen($number) > 15) {
        $return = FALSE;
    }
    return $return;
}

// Start w/ choice of mass blast or 1-1 message
$choice = read_stdin('Options: 1 for SMS blast, 2 for 1-on-1 message: ', array('1', '2'));

switch ($choice) {
    // MASS SMS STORM
    case 1:
        // Get the numbers to send, from a local csv file
        $numbers = NULL;
        while ($numbers == NULL || !intval($numbers[0])) {
            $file_name = read_in_csv();
            $numbers = parse_csv($file_name);
        }

        // Removing anything that isn't a properly formatted phone number
        $removed = array();
        $orig_count = 0;
        foreach ($numbers as $key => $number) {
            $number = clean_number($number);
            if (intval($number)) {
                $orig_count++;
            }
            if (!real_phone_number($number)) {
                unset($numbers[$key]);
                if (intval($number)) {
                    $removed[] = $number;
                }
            } else {
                $numbers[$key] = $number;
            }
        }

        // What to do, based on number of illegitimate numbers
        $removed_count = count($removed);
        if ($removed_count) {
            if ($removed_count == $orig_count) { // Return if there are no legitimate numbers to send to!
                fwrite(STDOUT, "Stop it. There are no real numbers to send to. I'm out.\n");
                return;
            }
            fwrite(STDOUT, "Removed ".$removed_count." phone number(s) because they weren't really real:\n");
            foreach ($removed as $number) {
                fwrite(STDOUT, $number."\n");
            }
        }

        send_message($numbers);
        break;

    // INDIVIDUAL SMS MESSAGE
    case 2:
        $number = NULL;
        while (!real_phone_number($number)) {
            $number = clean_number(read_stdin('Number to send message to: ', ''));
            if (!real_phone_number($number)) {
                fwrite(STDOUT, "Sorry, that wasn't a real phone number. Try again.\n");
            }
        }

        send_message(array($number));
        break;
}

?>
