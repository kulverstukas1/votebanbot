<?php
/*
    Author: Kulverstukas
    Date: 2014.07.18
    Website: 9v.lt, Evilzone.org
    Description:
        This is a class with various utility functions.
*/

class Utils {
    
    private $whitelist = 'whitelist.txt'; // a list of people that cannot be banned with this bot
    private $allowedToVote = 'allowed_to_vote.txt'; // a list of people that are allowed to vote
    private $admins = 'admins.txt'; // a list of people that are allowed to administrate the bot
    private $logFolder = "logs";
    private $logFile = "%s-%s-%s.txt"; // don't touch!
    private $argv = array();
    private $whitelistArr = array();
    private $allowedToVoteArr = array();
    private $adminArr = array();
    
    // default values
    private $config = array(
                        'server' => 'irc.evilzone.org',
                        'port' => 6667, // don't use SSL ports
                        'nick' => 'VotebanBot',
                        'name' => 'Voteban',
                        'channel' => '#test',
                        'registered' => false, // change it to False if your bot name isn't registered
                        'nickserv_passwd' => '', // if your bot is registered, this is where you put the password
                        'timeout' => 1, // time in minutes for how long the voting should last
                        'minimum_users' => 5, // do not decrease this!
                        'percentage' => 50, // how many votes should the bot require in percents
                        'allow_guest_votes' => false,
                        'send_notice' => true,
                        'verbose_output' => false
                    );
    
    //=============================================================
    function __construct($argv) {
        $this->argv = $argv;
        $this->readLists();
    }
    //=============================================================
    // Calculate how many votes are required based on the percentage
    // -3 is to make up for 3 people that cannot vote:
    // 1 bot, 1 that is getting banned and 1 that initiated the voting
    public function calculateVoteCount($numOfPeople, $percentage) {
        return floor(($numOfPeople-3)*$percentage/100);
    }
    //=============================================================
    // removes all the user mode symbols to make clean nicknames
    public function stripModeSymbols($nickname) {
        $symbolArr = array('&', '+', '%', '@', ':', '~');
        for ($i = 0; $i < count($symbolArr); $i++) {
            $nickname = str_replace($symbolArr[$i], '', $nickname);
        }
        return trim($nickname);
    }
    //=============================================================
    // Reads lists into arrays, doesn't support in-line comments
    public function readLists() {
        // reads whitelist
        if (file_exists($this->whitelist)) {
            $this->whitelistArr = array(); // reset
            $fHandle = fopen($this->whitelist, "r");
            if ($fHandle) {
                while (($line = fgets($fHandle)) !== false) {
                    if (($line[0] != ';') && (trim($line) != '')) {
                        array_push($this->whitelistArr, trim($line));
                        //print $line;
                    }
                }
                fclose($fHandle);
            } else {
                print "[-] Error opening ".$this->whitelist." file\n";
                exit(1);
            }
        } else { // file doesn't exist, let's create it!
            $this->createWhitelistFile();
        }
        
        // read allowed to vote list
        if (file_exists($this->allowedToVote)) {
            $this->allowedToVoteArr = array(); // reset
            $fHandle = fopen($this->allowedToVote, "r");
            if ($fHandle) {
                while (($line = fgets($fHandle)) !== false) {
                    if (($line[0] != ';') && trim($line) != '') {
                        array_push($this->allowedToVoteArr, trim($line));
                        // print $line;
                    }
                }
                fclose($fHandle);
            } else {
                print "[-] Error opening ".$this->allowedToVoteArr." file\n";
                exit(1);
            }
        } else {
            $this->createAllowedToVoteFile();
        }
        
        // read the admin list
        if (file_exists($this->admins)) {
            $this->adminsArr = array(); // reset
            $fHandle = fopen($this->admins, "r");
            if ($fHandle) {
                while (($line = fgets($fHandle)) !== false) {
                    if (($line[0] != ';') && trim($line) != '') {
                        array_push($this->adminArr, trim($line));
                        // print $line;
                    }
                }
                fclose($fHandle);
            } else {
                print "[-] Error opening ".$this->admins." file\n";
                exit(1);
            }
        } else {
            $this->createAdminFile();
        }
        
        if ($this->config['verbose_output']) {
            print "[+] Lists re-read\n";
        }
    }
//=============================================================
    private function createWhitelistFile() {
        $fHandle = fopen($this->whitelist, "w");
        fwrite($fHandle, "; This is a list that contains names that cannot be banned with this bot\r\n\r\n");
        fclose($fHandle);
    }
    
    private function createAllowedToVoteFile() {
        $fHandle = fopen($this->allowedToVote, "w");
        fwrite($fHandle, "; This is a list that contains people that are allowed to vote\r\n\r\n");
        fclose($fHandle);
    }
    private function createAdminFile() {
        $fHandle = fopen($this->admins, "w");
        fwrite($fHandle, "; This is a list that contains administrators that are allowed to use commands like !quit or !reload\r\n\r\n");
        fclose($fHandle);
    }
//=============================================================
    // function to add to the lists
    public function addToWhiteList($nickname) {
        $nickname = strtolower($nickname);
        if (!in_array($nickname, $this->whitelistArr)) {
            file_put_contents($this->whitelist, $nickname."\r\n", FILE_APPEND);
            array_push($this->whitelistArr, $nickname);
        }
    }
    public function addToAllowedToVote($nickname) {
        $nickname = strtolower($nickname);
        if (!in_array($nickname, $this->allowedToVoteArr)) {
            file_put_contents($this->allowedToVote, $nickname."\r\n", FILE_APPEND);
            array_push($this->allowedToVoteArr, $nickname);
        }
    }
    public function addToAdminList($nickname) {
        $nickname = strtolower($nickname);
        if (!in_array($nickname, $this->adminArr)) {
            file_put_contents($this->admins, $nickname."\r\n", FILE_APPEND);
            array_push($this->adminArr, $nickname);
        }
    }
//=============================================================
    // functions to remove from the lists
    public function removeFromWhiteList($nickname) {
        $nickname = strtolower($nickname);
        if (in_array($nickname, $this->whitelistArr)) {
            $data = file_get_contents($this->whitelist);
            // print $data."\n";
            if ($data != false) {
                for ($i = 0; $i < count($this->whitelistArr); $i++) {
                    if ($this->whitelistArr[$i] == $nickname) {
                        $this->whitelistArr[$i] = '';
                        break;
                    }
                }
                $newData = str_replace($nickname."\r\n", "", $data);
                // print $newData."\n";
                file_put_contents($this->whitelist, $newData);
            }
        }
    }
    public function removeFromAllowedToVote($nickname) {
        $nickname = strtolower($nickname);
        if (in_array($nickname, $this->whitelistArr)) {
            $data = file_get_contents($this->allowedToVote);
            // print $data."\n";
            if ($data != false) {
                for ($i = 0; $i < count($this->allowedToVoteArr); $i++) {
                    if ($this->allowedToVoteArr[$i] == $nickname) {
                        unset($this->allowedToVoteArr[$i]);
                        break;
                    }
                }
                $newData = str_replace($nickname."\r\n", "", $data);
                // print $newData."\n";
                file_put_contents($this->allowedToVote, $newData);
            }
        }
    }
    public function removeFromAdminList($nickname) {
        $nickname = strtolower($nickname);
        if (in_array($nickname, $this->adminArr)) {
            $data = file_get_contents($this->admins);
            // print $data."\n";
            if ($data != false) {
                for ($i = 0; $i < count($this->adminArr); $i++) {
                    if ($this->adminArr[$i] == $nickname) {
                        unset($this->adminArr[$i]);
                        break;
                    }
                }
                $newData = str_replace($nickname."\r\n", "", $data);
                // print $newData."\n";
                file_put_contents($this->admins, $newData);
            }
        }
    }
//=============================================================
    // functions to check if the given user is in the lists
    public function isInWhitelist($nickname) {
        $nickname = strtolower($nickname);
        return in_array($nickname, $this->whitelistArr);
    }
    public function isAllowedToVote($nickname) {
        $nickname = strtolower($nickname);
        return in_array($nickname, $this->allowedToVoteArr);
    }
    public function isAdmin($nickname) {
        // print $nickname;
        $nickname = strtolower($nickname);
        return in_array($nickname, $this->adminArr);
    }
//=============================================================
    // Extracts the sender nickname
    public function extractNickname($rawOutput) {
        $end = strpos($rawOutput, '!');
        return strtolower(substr($rawOutput, 1, $end-1));
    }
//=============================================================
    // Extracts the reason for banning
    public function extractReason($rawOutput) {
        $arr = $this->splitStr(' ', $rawOutput);
        $reason = "";
        for ($i = 5; $i < count($arr); $i++) {
            $reason .= ' '.$arr[$i];
        }
        // print $reason;
        return trim($reason);
    }
//=============================================================
    // Extracts user hostname
    public function extractHostname($rawOutput) {
        $start = strpos($rawOutput, '@')+1;
        // print $start."\n";
        $end = strpos($rawOutput, ' ');
        // print $end."\n";
        // print $end-strlen($rawOutput)."\n";
        if ($end) { // delimiter was found
            return strtolower(substr($rawOutput, $start, $end-strlen($rawOutput)));
        } else {
            return strtolower(substr($rawOutput, $start));
        }
    }
//=============================================================
    public function shouldSendNotice() {
        return $this->config['send_notice'];
    }
//=============================================================
    public function connectionAborted($rawOutput) {
        return $this->startsWith($rawOutput, 'ERROR :Closing Link');
    }
//=============================================================
    public function wasKicked($rawOutput, $botName) {
        return $this->contains(strtolower($rawOutput), 'kick') && $this->contains(strtolower($rawOutput), ' '.strtolower($botName).' ');
    }
//=============================================================
    // Helper function to check if a string contains a substring
    public function contains($where, $what) {
        return (strpos($where, $what) !== false);
    }
//=============================================================
    // Helper function to check if a string starts with a substring
    public function startsWith($where, $what) {
        return $where === "" || strpos($where, $what) === 0;
    }
//=============================================================
    public function endsWith($haystack, $needle) {
        return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
    }
//=============================================================
    // Splits the input and trims every element in the array
    public function splitStr($char, $string) {
        return array_map('strtolower', array_map('trim', explode($char, $string)));
    }
//=============================================================
    public function readConfig() {
        // $config = array(); // reset
        if (count($this->argv) >= 2) {
            if (file_exists($this->argv[1])) {
                $fHandle = fopen($this->argv[1], "r");
                if ($fHandle) {
                    while (($line = fgets($fHandle)) !== false) {
                        $tmp = array_map('trim', explode("=", $line));
                        if (($line[0] != ';') && trim($line) != '') {
                            switch (strtolower($tmp[0])) { 
                                case 'server': $this->config['server'] = $tmp[1]; break;
                                case 'port': $this->config['port'] = intval($tmp[1]); break;
                                case 'nick': $this->config['nick'] = $tmp[1]; break;
                                case 'name': $this->config['name'] = $tmp[1]; break;
                                case 'channel': $this->config['channel'] = $tmp[1]; break;
                                case 'registered': $this->config['registered'] = (strtolower($tmp[1]) == "yes") ? true : false; break;
                                case 'nickserv_passwd': $this->config['nickserv_passwd'] = $tmp[1]; break;
                                case 'timeout': $this->config['timeout'] = intval($tmp[1]); break;
                                case 'minimum_users': $this->config['minimum_users'] = intval($tmp[1]); break;
                                case 'percentage': $this->config['percentage'] = intval($tmp[1]); break;
                                case 'allow_guest_votes': $this->config['allow_guest_votes'] = (strtolower($tmp[1]) == "yes") ? true : false; break;
                                case 'send_notice': $this->config['send_notice'] = (strtolower($tmp[1]) == "yes") ? true : false; break;
                                case 'verbose_output': $this->config['verbose_output'] = (strtolower($tmp[1]) == "yes") ? true : false; break;
                            }
                            // print $tmp[1]."\n";
                        }
                    }
                    fclose($fHandle);
                }
            } else {
                print "[-] File doesn't exist!\n";
                exit(1);
            }
        } else {
            print "[-] No config given!\n";
            exit(1);
        }
        return $this->config;
    }
//=============================================================
    public function changeConfig($configStr, $value) {
        $data = file_get_contents($this->argv[1]);
        $newData = "";
        // var_dump($this->config['send_notice']);
        switch ($configStr) {
            case 'timeout': if (is_numeric($value)) {
                                $newData = str_replace('timeout='.$this->config['timeout'], 'timeout='.$value, $data);
                                $this->config['timeout'] = $value;
                            }     
                            break;
            case 'minimum_users': if (is_numeric($value)) {
                                      $newData = str_replace('minimum_users='.$this->config['minimum_users'], 'minimum_users='.$value, $data);
                                      $this->config['minimum_users'] = $value;
                                  }
                                  break;
            case 'percentage': if (is_numeric($value)) {
                                   $newData = str_replace('percentage='.$this->config['percentage'], 'percentage='.$value, $data);
                                   $this->config['percentage'] = $value;
                               }
                               break;
            case 'allow_guest_votes': if (($value == 'yes') || ($value == 'no')) {
                                        $currValue = $this->config['allow_guest_votes'] ? 'yes' : 'no';
                                        $newData = str_replace('allow_guest_votes='.$currValue, 'allow_guest_votes='.$value, $data);
                                        $this->config['allow_guest_votes'] = (strtolower($value) == "yes") ? true : false;
                                      }
                                      break;
            case 'send_notice': if (($value == 'yes') || ($value == 'no')) {
                                    $currValue = $this->config['send_notice'] ? 'yes' : 'no';
                                    $newData = str_replace('send_notice='.$currValue, 'send_notice='.$value, $data);
                                    $this->config['send_notice'] = (strtolower($value) == "yes") ? true : false;
                                }
                                break;
        }
        if ($newData != "") {
            file_put_contents($this->argv[1], $newData);
            return $this->config;
        } else {
            return null;
        }
    }
//=============================================================
    public function logToFileAndPrint($entry) {
        $formattedFile = sprintf($this->logFile, $this->config['server'], $this->config['channel'], $this->config['nick']);
        $currTime = '['.date('Y.m.d, G:i:s').'] ';
        if (!file_exists($this->logFolder)) {
            mkdir($this->logFolder);
        }
        file_put_contents($this->logFolder.'/'.$formattedFile, $currTime.$entry."\r\n", FILE_APPEND);
        print $currTime.$entry."\n";
    }
//=============================================================
    public function formatTimeLeft($secs) {
        return strftime("%M:%S", ($this->config['timeout']*60)-$secs);
    }
//=============================================================
}

?>