<?php
/*
    Author: Kulverstukas
    Date: 2014.07.18
    Modified: 2014.08.30
    Website: 9v.lt, Evilzone.org
    Version: 0.6
    Description:
        A little IRC bot that helps maintain peace when no OP's are present by providing
        the ability to legally ban someone through voting.
*/

//=============================================================

include("Utils.php");

class VotebanBot {

    private $config = array();
    private $socket = null;
    private $userArr = array();
    private $utils = null;
    private $linkClosed = false; // could use a better solution doe
    private $wasKicked = false;
    private $argv = array();
    private $DEBUG_MODE = false; // for devs
    
    //=============================================================
    function __construct($argv) {
        $this->utils = new Utils($argv);
        $this->argv = $argv;
        $this->config = $this->utils->readConfig($argv);
        $this->initiate();
    }
    //=============================================================
    // This right here prevent recursion to happen
    private function initiate() {
        $this->connectToServer();
        $this->begin();
    }
    //=============================================================
    // Connects to server
    private function connectToServer() {
        $this->socket = fsockopen($this->config['server'], $this->config['port']);
        if ($this->socket != null) {
            stream_set_timeout($this->socket, 1);
            // stream_set_blocking($this->socket, 0); // set the socket as non-blocking
            fwrite($this->socket, 'USER '.$this->config['nick'].' bot.heaven '.$this->config['nick'].' :'.$this->config['name']."\r\n");
            // usleep(100);
            fwrite($this->socket, 'NICK '.$this->config['nick']."\r\n");

            // whenever we send shit, we also have to read what comes out, otherwise the socket refuses input while it has data to give
            while (true) {
                $tmp = fgets($this->socket);
                $lineParts = $this->utils->splitStr(' ', $tmp);
                // print_r($lineParts); die();
                if ($lineParts[0] == 'ping') {
                    $this->sendPong($lineParts[1], $this->socket);
                    break;
                } else if ($lineParts[0] == 'error') {
                    $this->utils->logToFileAndPrint(trim($tmp));
                    $this->utils->logToFileAndPrint("[*] Waiting 1 minute");
                    sleep(60);
                    $this->initiate();
                } else {
                    break;
                }
            }
            // if the link gets closed, this is where it'll go down with a bad name
            // $tmp = fgets($this->socket);
            // if ($this->utils->connectionAborted($tmp)) {
                // print "[-] Link was closed!\n";
                // print $tmp;
                // exit(1);
            // }
            $this->utils->logToFileAndPrint("[+] Connected to ".$this->config['server'].":".$this->config['port']);
            // usleep(100);
            
            // print $this->config['nickserv_passwd']."\n";
            // print $this->config['registered']."\n";
            // exit(1);
            if ($this->config['registered']) {
                $output = '';
                while (true) {
                    if ($this->utils->contains(fgets($this->socket), "376")) { // end of motd
                        break;
                    }
                }
                fwrite($this->socket, 'PRIVMSG NICKSERV IDENTIFY '.$this->config['nickserv_passwd']."\r\n");
                $this->utils->logToFileAndPrint("[+] Identified with NICKSERV");
                // usleep(100);
            }
            fwrite($this->socket, 'MODE '.$this->config['nick']." +B\r\n");
            
            $this->joinChannel();
            
        } else {
            $this->utils->logToFileAndPrint("[-] Something went wrong opening the socket :(");
            exit(1);
        }
    }
    //=============================================================
    // Joins a set channel
    private function joinChannel() {
        fwrite($this->socket, 'JOIN '.$this->config['channel']."\r\n");
        while (true) {
            $rawOutput = fgets($this->socket);
            if (($this->DEBUG_MODE) && ($rawOutput != '')) {
                print $rawOutput;
            }
            if (explode(' ', $rawOutput)[0] == 'PING') {
                $this->sendPong(explode(' ', $rawOutput)[1]);
            } else if ($this->utils->contains($rawOutput, '366')) { // end of /NAMES list
                $this->utils->logToFileAndPrint("[+] Joined ".$this->config['channel']);
                break;
            } else if ($this->utils->contains($rawOutput, '471')) { // ERR_CHANNELISFULL
                $this->utils->logToFileAndPrint("[-] Could not join ".$this->config['channel'].". Error: channel is full");
                $this->utils->logToFileAndPrint("[*] Waiting 1 minute");
                sleep(60); // retry every minute
                fwrite($this->socket, 'JOIN '.$this->config['channel']."\r\n");
            } else if ($this->utils->contains($rawOutput, '473')) { // ERR_INVITEONLYCHAN
                $this->utils->logToFileAndPrint("[-] Could not join ".$this->config['channel'].". Error: channel is invite only");
                $this->utils->logToFileAndPrint("[*] Waiting 1 minute");
                sleep(60);
                fwrite($this->socket, 'JOIN '.$this->config['channel']."\r\n");
            } else if ($this->utils->contains($rawOutput, '474')) { // ERR_BANNEDFROMCHAN
                $this->utils->logToFileAndPrint("[-] Could not join ".$this->config['channel'].". Error: banned from channel");
                $this->utils->logToFileAndPrint("[*] Waiting 1 minute");
                sleep(60);
                fwrite($this->socket, 'JOIN '.$this->config['channel']."\r\n");
            }
        }
    }
    //=============================================================
    // Start the core function of this bot
    private function startVoteban($banProfile, $initProfile) {
        $nameArr = $this->parseUsers();
        // print "\n".$banProfile['hostname']."\n";
        if (count($nameArr) >= $this->config['minimum_users']) {
            if (($banProfile['nickname'] != strtolower($this->config['nick'])) &&
                    !$this->utils->isInWhitelist($banProfile['nickname']) &&
                    !$this->utils->isAdmin($banProfile['nickname'])) {
                $this->utils->logToFileAndPrint("[+] Voteban started on ".$banProfile['nickname']." (".$banProfile['hostname'].") with reason \"".$initProfile['reason']."\" by ".$initProfile['nickname']." (".$initProfile['hostname'].")");
                if ($this->utils->shouldSendNotice()) {
                    fwrite($this->socket, 'NOTICE '.$this->config['channel'].' :Voteban started on '.$banProfile['nickname']." with reason: ".$initProfile['reason']."\r\n");
                } else {
                    fwrite($this->socket, 'PRIVMSG '.$this->config['channel'].' :Voteban started on '.$banProfile['nickname']." with reason: ".$initProfile['reason']."\r\n");
                }
                fwrite($this->socket, 'PRIVMSG '.$this->config['channel'].' :'.$this->utils->calculateVoteCount(count($nameArr), $this->config['percentage']).' votes are needed to end voting before '.$this->config['timeout'].' minute mark. The user will be banned when time runs out if !yes > !no. Give your vote with !yes or !no, you can only vote once.'."\r\n");
                $res = $this->countVotes($initProfile, $banProfile, $nameArr);
                if ($res['shouldBan'] && !$res['cancelled'] && !$res['tie']) {
                    $this->utils->logToFileAndPrint("[+] User ".$banProfile['nickname'].' ('.$banProfile['hostname'].") was banned with reason \"".$initProfile['reason']."\"");
                    fwrite($this->socket, 'PRIVMSG '.$this->config['channel'].' :Majority has found the user '.$banProfile['nickname']." guilty.\r\n");
                    fwrite($this->socket, 'MODE '.$this->config['channel'].' +b '.$banProfile['banmask']."\r\n");
                    fwrite($this->socket, 'KICK '.$this->config['channel'].' '.$banProfile['nickname'].' '.$initProfile['reason']."\r\n");
                } else if (!$res['shouldBan'] && !$res['cancelled'] && !$res['tie']) {
                    $this->utils->logToFileAndPrint("[+] User ".$banProfile['nickname'].' ('.$banProfile['hostname'].") was NOT banned with reason \"".$initProfile['reason']."\"");
                    fwrite($this->socket, 'PRIVMSG '.$this->config['channel'].' :Majority has found the user '.$banProfile['nickname']." NOT guilty.\r\n");
                } else if ($res['tie'] && !$res['cancelled']) {
                    $this->utils->logToFileAndPrint("[+] User ".$banProfile['nickname'].' ('.$banProfile['hostname'].") was NOT banned with reason \"".$initProfile['reason']."\"");
                    fwrite($this->socket, 'PRIVMSG '.$this->config['channel'].' :It\'s a tie, user '.$banProfile['nickname']." is not banned.\r\n");
                } else if ($res['cancelled']) {
                    fwrite($this->socket, 'PRIVMSG '.$this->config['channel'].' :Voteban was cancelled for '.$banProfile['nickname']."\r\n");
                }
            } else {
                $this->utils->logToFileAndPrint("[-] Voteban issued on ".$banProfile['nickname']." (".$banProfile['hostname'].") with reason \"".$initProfile['reason']."\" by ".$initProfile['nickname']." (".$initProfile['hostname'].")");
                fwrite($this->socket, 'PRIVMSG '.$this->config['channel'].' :'.$banProfile['nickname'].' cannot be banned.'."\r\n");
            }
        } else {
            fwrite($this->socket, 'PRIVMSG '.$this->config['channel'].' :Cannot initiate voting, need '.$this->config['minimum_users'].' or more users in the channel'."\r\n");
            $this->utils->logToFileAndPrint("[-] Not enough users to start voting, need ".$this->config['minimum_users']);
        }
    }
    //=============================================================
    // Goes into a loop for a set time period and counts votes
    private function countVotes($initProfile, $banProfile, $nameArr) {
        // print "\n".$initProfile['hostname']."\n";
        $timeout = $this->config['timeout']*60;
        $startTime = time();
        $yesCount = 0;
        $noCount = 0;
        $usersVoted = array();
        $hostnamesVoted = array();
        $res = array(
            'shouldBan' => false,
            'tie' => true,
            'cancelled' => false,
        );
        
        $rawOutput = "";
        // $timer = 0;
        while (!$this->linkClosed && !$this->wasKicked) {
            $rawOutput = fgets($this->socket);
            // print $rawOutput;
            // $timer++;
            // print $timer."\n";
            $lineParts = $this->utils->splitStr(' ', $rawOutput);
            $nickVoted = $this->utils->extractNickname($lineParts[0]);
            $votedHostname = $this->utils->extractHostname($lineParts[0]);
            // print $votedHostname."\n";
            // print $nickVoted;
            if ($lineParts[0] == 'ping') { // here we check if it is a PING command
                $this->sendPong($lineParts[1]);
            } else if ((count($lineParts) >= 4) && ($lineParts[3] == ':!cancel')) { // looks like the cancel command was issued, holy shit
                if ((($nickVoted == $initProfile['nickname']) && ($votedHostname == $initProfile['hostname'])) ||
                            ($this->utils->isAdmin($nickVoted) && $this->isAuthenticated($nickVoted))) {
                    $res['cancelled'] = true;
                    $this->utils->logToFileAndPrint("[+] Voteban was cancelled for user ".$banProfile['nickname']." (".$banProfile['hostname'].") by $nickVoted ($votedHostname)");
                    break;
                }
            } else if ((count($lineParts) >= 4) && ($lineParts[3] == ':!votestatus')) {
                fwrite($this->socket, "PRIVMSG ".$this->config['channel']." :Received $yesCount:$noCount (total: ".($yesCount+$noCount).") votes. Voting on ".$banProfile['nickname']." with reason: ".$initProfile['reason'].". Time left for voting: ".$this->utils->formatTimeLeft((time() - $startTime))."\r\n");
                if ($this->config['verbose_output']) {
                    $this->utils->logToFileAndPrint("[+] Responded to !votestatus command by $nickVoted ($votedHostname)");
                }
            } else if ((count($lineParts) >= 4) && (in_array($nickVoted, $nameArr)) && (($lineParts[3] == ':!yes') || ($lineParts[3] == ':!no'))) { // check if the dude was in the channel when voting began
                // if it doesn't allow guest votes, then confirm the name
                $validVote = false;
                if (!$this->config['allow_guest_votes']) {
                    $validVote = $this->utils->isAllowedToVote($initProfile['nickname']) &&
                                    $this->isAuthenticated($nickVoted) &&
                                    !in_array($nickVoted, $usersVoted) &&
                                    ($nickVoted != $initProfile['nickname']) &&
                                    ($nickVoted != $banProfile['nickname']);
                } else {
                    $validVote = !in_array($votedHostname, $hostnamesVoted) &&
                                    ($votedHostname != $initProfile['hostname']) &&
                                    ($votedHostname != $banProfile['hostname']);
                    // print $banProfile['hostname']."\n";
                    // print $votedHostname."\n";
                }
                
                if ($validVote) {
                    // print "valid_vote\n";
                    array_push($usersVoted, $nickVoted);
                    array_push($hostnamesVoted, $votedHostname);
                    switch ($lineParts[3]) {
                        case ':!yes': $yesCount++;
                            if ($this->config['verbose_output']) {
                                $this->utils->logToFileAndPrint("[+] Got yes! from user $nickVoted ($votedHostname)");
                            }
                            break;
                        case ':!no' : $noCount++;
                            if ($this->config['verbose_output']) {
                                $this->utils->logToFileAndPrint("[+] Got no! from user $nickVoted ($votedHostname)");
                            }
                            break;
                    }
                }
            }
            
            if ((time() - $startTime) >= $timeout) {
                break; // get the fuck out if time has passed
            }
            // $rawOutput = fgets($this->socket);
            $this->linkClosed = $this->utils->connectionAborted($rawOutput);
            // print $this->linkClosed;
            $this->wasKicked = $this->utils->wasKicked($rawOutput, $this->config['nick']);
        }
        if ($this->linkClosed || $this->wasKicked) {
            $this->begin(true); // go straight to handling the event, if the bot got kicked or something...
        } else {
            fwrite($this->socket, "PRIVMSG ".$this->config['channel']." :Voting has ended for user ".$banProfile['nickname']." (".$banProfile['hostname'].")\r\n");
            $res['shouldBan'] = $yesCount > $noCount;
            $res['tie'] = $yesCount == $noCount;
            return $res;
        }
    }
    //=============================================================
    // Parses the output of NAMES command and returns an array
    private function parseUsers() {
        fwrite($this->socket, "NAMES ".$this->config['channel']."\r\n");
        $nameArray = array();
        while (true) {
            $rawOutput = fgets($this->socket);
            if ($this->utils->contains($rawOutput, "353")) {
                $lineParts = explode(" ", $rawOutput);
                for ($i = 5; $i < count($lineParts); $i++) {
                    $strippedNick = $this->utils->stripModeSymbols($lineParts[$i]);
                    if ($strippedNick != "") {
                        array_push($nameArray, $strippedNick);
                    }
                }
            } else if ($this->utils->contains($rawOutput, "366")) {
                break; // the end of user list has been reached
            }
        }
        return array_map('strtolower', $nameArray);
    }
    //=============================================================
    // Builds an array profile about a user to be banned
    private function extractFromWhois($nickname) {
        fwrite($this->socket, 'WHOIS '.$nickname."\r\n");
        $lineParts = null;
        while (true) {
            $rawOutput = fgets($this->socket);
            if ($this->utils->contains($rawOutput, "311")) {
                $lineParts = $this->utils->splitStr(' ', $rawOutput);
            } else if ($this->utils->contains($rawOutput, "318")) {
                break;
            }
        }
        $nickProfile = array();
        if ($lineParts != null) {
            $nickProfile['nickname'] = $lineParts[3];
            $nickProfile['user'] = $lineParts[4];
            $nickProfile['hostname'] = $lineParts[5];
            $nickProfile['realname'] = substr($lineParts[7], 1);
            $nickProfile['banmask'] = '*!*'.$nickProfile['user'].'@*'.substr($nickProfile['hostname'], strpos($nickProfile['hostname'], '.'));
            // print $nickProfile['banmask']."\n";
            return $nickProfile;
        }
        
        return null;
    }
    //=============================================================
    // This processes the output that we gets from IRC
    private function processOutput() {
        /*
            This contains an array with information, we must check if we received a correct line. We need this:
                * 0 == Person who wrote, nickname is between : and ! at the start
                * 1 == Command, usualy it should be PRIVMSG
                * 2 == Channel where the message was sent
                * 3 == Actual message, starting with :
        */
        $rawOutput = '';
        while (!$this->linkClosed && !$this->wasKicked) { // yeah, infinite loop... :P
            $msg = $this->utils->splitStr(' ', $rawOutput); // splits, trims and makes it all lowercase
            if ($msg[0] == 'ping') { // here we check if it is a PING command
                $this->sendPong($msg[1]);
            // Do nothing with NOTICE and PM commands which can be abused to spam...
            // check if it's truely an array we need
            } else if ((count($msg) >= 4) && (($msg[1] != 'notice') && (strtolower($msg[2]) == $this->config['channel']))) {
                $initNickname = $this->utils->extractNickname($msg[0]);
                $initHostname = $this->utils->extractHostname($msg[0]);
                if ($msg[3] == ':!info') {
                    $this->printInfo($this->config['channel']);
                } else if ($msg[3] == ':!voteban') { // voteban began!
                    $initProfile['nickname'] = $initNickname;
                    $initProfile['hostname'] = $initHostname;
                    $initProfile['reason'] = $this->utils->extractReason($rawOutput);
                    // print "\n".$initProfile['hostname']."\n";
                    // another if... this doesn't allow people to ban themselves
                    if ((strlen($initProfile['reason']) <= $this->config['ignore_longer']) && ($msg[4] != $initProfile['nickname']) && ($initProfile['reason'] != '')) {
                        $canStartVoting = true;
                        if (!$this->config['allow_guest_votes']) { // see if the user has authenticated, since no guest votes are allowed
                            $canStartVoting = $this->utils->isAllowedToVote($initProfile['nickname']) && $this->isAuthenticated($initProfile['nickname']);
                        }
                        if ($canStartVoting) {
                            $banProfile = $this->extractFromWhois($msg[4]);
                            if ($banProfile != null) {
                                $this->startVoteban($banProfile, $initProfile);
                            } else {
                                fwrite($this->socket, "PRIVMSG ".$this->config['channel']." :User ".$msg[4]." is not online.\r\n");
                            }
                        }
                    }
                } else if ($msg[3] == ':!reload') {
                    if ($this->utils->isAdmin($initNickname) && $this->isAuthenticated($initNickname)) {
                        $this->utils->logToFileAndPrint("[+] $initNickname re-read lists");
                        $this->utils->readLists();
                        fwrite($this->socket, "NOTICE $initNickname :Lists re-read\r\n");
                    } else {
                        $this->utils->logToFileAndPrint("[!] $initNickname ($initHostname) tried to use !reload command");
                    }
                } else if ($msg[3] == ':!add') {
                    if (count($msg) >= 6) {
                        if ($this->utils->isAdmin($initNickname) && $this->isAuthenticated($initNickname)) {
                            switch (strtolower($msg[4])) {
                                case 'whitelist': $this->utils->addToWhiteList($msg[5]);
                                                  $this->utils->logToFileAndPrint('[+] Added '.$msg[5].' to whitelist by '.$initNickname);
                                                  fwrite($this->socket, 'NOTICE '.$initNickname.' :Added '.$msg[5].' to whitelist'."\r\n");
                                                  break;
                                                  
                                case 'votelist': $this->utils->addToAllowedToVote($msg[5]);
                                                  $this->utils->logToFileAndPrint('[+] Added '.$msg[5].' to allowed to vote list by '.$initNickname);
                                                  fwrite($this->socket, 'NOTICE '.$initNickname.' :Added '.$msg[5].' to allowed to vote list'."\r\n");
                                                  break;
                                                  
                                case 'adminlist': $this->utils->addToAdminList($msg[5]);
                                                  $this->utils->logToFileAndPrint('[+] Added '.$msg[5].' to admin list by '.$initNickname);
                                                  fwrite($this->socket, 'NOTICE '.$initNickname.' :Added '.$msg[5].' to admin list'."\r\n");
                                                  break;
                            }
                        } else {
                            $this->utils->logToFileAndPrint("[!] $initNickname ($initHostname) tried to use !add command");
                        }
                    }
                } else if ($msg[3] == ':!remove') {
                    if (count($msg) >= 6) {
                        if ($this->utils->isAdmin($initNickname) && $this->isAuthenticated($initNickname)) {
                            switch (strtolower($msg[4])) {
                                case 'whitelist': $this->utils->removeFromWhiteList($msg[5]);
                                                  $this->utils->logToFileAndPrint('[+] Removed '.$msg[5].' from whitelist by '.$initNickname);
                                                  fwrite($this->socket, 'NOTICE '.$initNickname.' :Removed '.$msg[5].' from whitelist'."\r\n");
                                                  break;
                                                  
                                case 'votelist': $this->utils->removeFromAllowedToVote($msg[5]);
                                                  $this->utils->logToFileAndPrint('[+] Removed '.$msg[5].' from allowed to vote list by '.$initNickname);
                                                  fwrite($this->socket, 'NOTICE '.$initNickname.' :Removed '.$msg[5].' from allowed to vote list'."\r\n");
                                                  break;
                                                  
                                case 'adminlist': $this->utils->removeFromAdminList($msg[5]);
                                                  $this->utils->logToFileAndPrint('[+] Removed '.$msg[5].' from admin list by '.$initNickname);
                                                  fwrite($this->socket, 'NOTICE '.$initNickname.' :Removed '.$msg[5].' from admin list'."\r\n");
                                                  break;
                            }
                        } else {
                            $this->utils->logToFileAndPrint("[!] $initNickname ($initHostname) tried to use !remove command");
                        }
                    }
                } else if ($msg[3] == ':!change') {
                    if (count($msg) >= 6) {
                        if ($this->utils->isAdmin($initNickname) && $this->isAuthenticated($initNickname)) {
                            $newConfig = $this->utils->changeConfig($msg[4], $msg[5]);
                            if ($newConfig != null) {
                                $this->config = $newConfig;
                                $this->utils->logToFileAndPrint('[+] Changed config property "'.$msg[4].'" to "'.$msg[5].'"');
                                fwrite($this->socket, 'NOTICE '.$initNickname.' :Changed config property "'.$msg[4].'" to "'.$msg[5].'"'."\r\n");
                            } else {
                                $this->utils->logToFileAndPrint('[-] Invalid data for property "'.$msg[4].'"');
                                fwrite($this->socket, 'NOTICE '.$initNickname.' :Invalid data for property "'.$msg[4].'"'."\r\n");
                            }
                        } else {
                            $this->utils->logToFileAndPrint("[!] $initNickname ($initHostname) tried to use !change command");
                        }
                    }
                } else if ($msg[3] == ':!reconfig') {
                    $initNickname = $this->utils->extractNickname($msg[0]);
                    if ($this->utils->isAdmin($initNickname) && $this->isAuthenticated($initNickname)) {
                        $this->config = $this->utils->readConfig($this->argv);
                        $this->utils->logToFileAndPrint('[+] '.$initNickname.' re-read configs');
                        fwrite($this->socket, 'NOTICE '.$initNickname.' :Re-read configs'."\r\n");
                    } else {
                        $this->utils->logToFileAndPrint("[!] $initNickname ($initHostname) tried to use !reconfig command");
                    }
                } else if ($msg[3] == ':!uptime') {
                    $initNickname = $this->utils->extractNickname($msg[0]);
                    if ($this->utils->isAdmin($initNickname) && $this->isAuthenticated($initNickname)) {
                        $uptime = $this->utils->getUptime();
                        fwrite($this->socket, 'NOTICE '.$initNickname.' :'.$uptime."\r\n");
                        if ($this->config['verbose_output']) {
                            $this->utils->logToFileAndPrint("[+] Responded to !uptime command by $initNickname ($initHostname)");
                        }
                    } else {
                        $this->utils->logToFileAndPrint("[!] $initNickname ($initHostname) tried to use !uptime command");
                    }
                }
            }
            $rawOutput = fgets($this->socket);
            if (($this->DEBUG_MODE) && ($rawOutput != '')) {
                print $rawOutput;
            }
            $this->linkClosed = $this->utils->connectionAborted($rawOutput);
            // print $this->linkClosed;
            $this->wasKicked = $this->utils->wasKicked($rawOutput, $this->config['nick']);
        }
    }
    //=============================================================
    // Checks if given user is logged in, returns true if it is
    private function isAuthenticated($nickname) {
        fwrite($this->socket, 'PRIVMSG NICKSERV :STATUS '.$nickname."\r\n");
        $msg = '';
        while (true) {
            $msg = fgets($this->socket);
            // print $msg;
            if ($this->utils->contains($msg, "NOTICE")) {
                break;
            }
        }
        return (explode(" ", $msg)[5] == "3");
    }
    //=============================================================
    // Responds to PING
    private function sendPong($uid) {
        fwrite($this->socket, 'PONG '.$uid."\r\n");
        if ($this->config['verbose_output']) {
            print "[+] Responded to PING\n"; // don't log this into file, we don't need it
        }
    }
    //=============================================================
    // Disconnects from the server cleanly
    private function disconnect() {
        fwrite($this->socket, "QUIT\r\n");
        fclose($this->socket);
        $this->utils->logToFileAndPrint("[*] Disconnected");
        exit(0);
    }
    //=============================================================
    // Responds to !info command
    private function printInfo($channel) {
        fwrite($this->socket, 'PRIVMSG '.$channel.' :Voteban bot, Kulverstukas, 2014. Coded in PHP.'."\r\n");
        usleep(100);
        fwrite($this->socket, 'PRIVMSG '.$channel.' :Usage: '.
                        'Initiate voteban: "!voteban nickname reason". '.
                        'Vote: !yes or !no when voting begins.'."\r\n");
        if ($this->config['verbose_output']) {
            $this->utils->logToFileAndPrint("[+] Responded to !info command");
        }
    }
    //=============================================================
    // Base function that receives data from the socket and acts accordingly
    private function begin($bypassProcessing = false) {
        if (!$bypassProcessing) {
            $this->processOutput();
        }
        
        if ($this->linkClosed) {
            $this->utils->logToFileAndPrint("[!] Link was closed, reconnecting...");
            $this->linkClosed = false;
            // $this->disconnect();
            $this->initiate();
        }
        
        if ($this->wasKicked) {
            $this->utils->logToFileAndPrint("[!] Got kicked from ".$this->config['channel']);
            $this->wasKicked = false;
            $this->joinChannel();
            $this->processOutput();
        }
    }
}
//=============================================================

// start everything
new VotebanBot($argv);

?>
<html>
<h4>Voteban bot cannot run in a browser.</h4>
</html>
