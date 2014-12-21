Voteban bot documentation v0.6


+------------------------------+
| Author: Kulverstukas         |
| Date: 2014.07.18             |
| Modified: 2014.08.30         |
| Website: 9v.lt, Evilzone.org |
| Version: 0.6                 |
+------------------------------+


What is in here:
 * 0x01.....Brief description.
 * 0x02.....User and admin commands.
 * 0x03.....Operation modes.
 * 0x04.....Configuration.
 * 0x05.....Lists.
 * 0x06.....Setting up.
 * 0x07.....How the fuck does it work?

 
~ 0x01 - Brief description
The Voteban IRC bot idea derived from the all popular voteban functionality in Counter-Strike and maybe other games to let users decide if some douche should be banned, or when no admin is around to deliver justice. So this little IRC bot helps maintain peace when no OP's are present by providing the ability to legally ban someone through voting.


~ 0x02 - User and admin commands
There are several commands available separately for a user and a bot admin.

~~ 0x021 - User commands
 * !info == provides basic information about the bot when voting is not happening.
 * !voteban <nickname> <reason> == initiates a voting session when voting is not happening. Additionally this command requires a nickname and a reason.
 * !cancel == cancels a voting session.
 * !yes == votes yes when voting is happening.
 * !no == votes no is happening.
 * !votestatus == gives information about current voting session, such as time left for voting and vote count.
 
~~ 0x022 - Admin commands
 * !reload == re-reads the lists from file to memory.
 * !add whitelist/votelist/adminlist <nickname> == adds the specified nickname to the speficied list and saves to a file.
 * !remove whitelist/votelist/adminlist <nickname> == removes the specified nickname from the speficied list and saves to a file.
 * !change == change a configuration option. Not all options on the file can be changed through IRC interface. What can be changed are timeout, minimum_users, percentage, allow_guest_votes, send_notice.
 * !reconfig == re-reads the configuration from file to memory. Not all values gets reloaded, some changes require the bot to be killed and started again.
 * !cancel == also a user command, it allows any admin to end the voting session.
 * !uptime == shows for how long the bot is connected.


~ 0x03 - Operation modes
The bot can work in two modes - allow guest votes or not. Which to choose depends on the situation of the channel this bot will be in.
Basically, if the bot allows guest votes, then once voting begins, anyone in the channel can vote, doesn't have to be authenticated with nickserv. This is potentially dangerous as it allows for trollvoting easily, but it's the most reliable option, however.
If the bot does not allow guest votes, then only people in the allowed_to_vote.txt list are allowed to vote. They have to be authenticated with services. While this is the most reliable way of counting votes, it reduces the amount of votes that can be gathered as it restricts voting only to the listed people.


~ 0x04 - Configuration
The bot is configurable, all of the configs are in the file configs.txt, however the name does not matter. When running the bot you must specify the configuration file to use, this allows to have multiple config files for quick swapping.
In the included configuration file, every value is explain with a comment which starts with ;. The bot doesn't support for inline comments, only those that start in a new line.


~ 0x05 - Lists
Additional control is done through lists. There are 3 lists - admins.txt, allowed_to_vote.txt and whitelist.txt.
Admin list contains nicknames that are considered to be bot admins, they can issue admin commands (and shit...). Must be authenticated with services to be considered valid.
Allowed_to_vote list contains nicknames that can vote when the bot is in a restricted voting mode (doesn't allow guest votes).
Whitelist contains nicknames that cannot be banned. Additionally to this, the bot cannot ban himself or those in the admin list as well. So nicknames in the admin list doesn't need to be repeated in the whitelist.


~ 0x06 - Setting up
Setting up is pretty easy. Download and install PHP5, run the bot with a command "php Voteban_bot.php configs.txt". On linux if you don't want to have the output and make it run in the background, use the command "php Voteban_bot.php configs.txt > /dev/null &".


~ 0x07 - How the fuck does it work?
The way it works is simple enough. User commands doesn't need users to be authenticated, except for when voting begins. Admins commands require all of the bot admins to be authenticated with nickserv.
When a command is issued to start voting, the bot first checks if a user exists by whoising and reading the output. If the user exists, it uses that output to build a profile for the user to be banned. After this point user can change his nickname or leave if he wants to, he will still get banned because the bot now has his details in memory.
When voting begins, it lasts for whatever minutes there is in the configuration file. Until that that time, by default the bot requires 50% votes of all users in the channel to end current voting session and ban the unwanted user prematurely. After time runs out, the bot compares received votes and if there are more yes than no, user gets banned. Otherwise he gets to stay. Voting does not begin if there are less people in the channel than set in the configs - default is 5 and this number should not be changed, otherwise the bot might not work as expected with low user count. The bot controls trollvoting by having a list of people that have already voted in 2 separate arrays, one for nicknames and one for hostnames.
The bot is able to detect when it gets kicked out of the channel and attempts to join again. If the channel is locked or otherwise unavailable, it waits until he can join again.
Bot nickname has to be registered, otherwise he won't be able to send notices to users using certain commands.