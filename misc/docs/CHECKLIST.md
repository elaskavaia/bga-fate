# Pre-release checklist
## ''' License '''
[ x] BGA must have a license for the game for a project to be moved to production, even to alpha. If you don't have license yet you can continue checking other stuff from the list below, but at the end it cannot be moved until license situation is cleared.
## ''' Metadata and graphics '''
[x] [[Game_meta-information: gameinfos.inc.php]] has correct and up to date information about the game.
[ x ] Game box graphics is 3D version of the game box (if available) and publisher icon is correct (see [[Game art: img directory]]). Space around the box has to be transparent, not white.
[ x ] You have added the requested images in the Game Metadata Manager to make the game page pretty
[x] There are no images in the img directory that are not needed anymore
[x] Multiple images (i.e. cards) are compressed in "Sprite" (see [[Game art: img directory]])
[x] Each image should not exceed 4Mb
[x] Total size should not exceed 15Mb, image compression should be used otherwise (it also helps a lot to re-encode images as indexed palette vs RBG). If you have legitimate reason to have more than 15Mb (i.e. expansions), you have to make a note of that when requesting move to alpha
[x] If you use extra fonts, they should be freeware (please include a .txt with the licence information in addition to the font files)
## ''' Server side '''
[x] When giving their turn to a player, you give them some extra time with the giveExtraTime() function
[x] Game progression is implemented (getGameProgression() in php)
[x] Zombie turn is implemented (zombieTurn() in php). For more details about how the Zombie code should work and be tested, see [[Zombie Mode|Zombie mode]].
[x] You have defined and implemented some meaningful statistics for your game (i.e. total points, point from source A, B, C...)
[x] Game has meaningful notification messages (but don't overkill it, more user logs will slow down the loading)
[x] You implemented tiebreaking (using aux score field) and updated tiebreaker description in meta-data
[x] Database: make sure you do not programmatically manage transactions or call queries that change database schema or even TRUNCATE (queries that would cause implicit commit) during normal game opearations
[x] Database: make sure you DB schema would be sufficient to complete the game and good enough for possible expantions, changing db schema schema after the release even in alpha is very challenging (it is possible but much better if you don't need to deal with it)
## ''' Client side '''
[x] Check that you use ajaxcall/bgaPerformAction only on player actions and never initiated programmatically. Otherwise, your code will very likely create race conditions resulting in deadlocks or other errors. This can also break replays and tutorials. ''Exception: sometimes you can do no-op moves with timeouts (i.e. user has only one choice, but its unwise to reveal this information by skipping user turn), timeout has to be canceling itself if state transition happen automaticaly (i.e. during replay)''
## ''' User Interface '''
[x] Review BGA UI design Guidelines [[BGA_Studio_Guidelines]]
[x] Check all your English messages for proper use of punctuation, capitalization, usage of present tense in notification (not past) and gender neutrality. See [[Translations]] for English rules.
[x] If the elements in your game zone don't occupy all the available horizontal space, '''they should be centered'''.
[x] If your game elements become blurry or pixellated when using the browser zoom, you may want to consider [[Game_art:_img_directory##Use_background-size | higher resolution images with background-size]]
[  ] Non-self explanatory graphic elements should have tooltips
[x] Strings in your source code are ready for translation. See [[Translations]]. You can generate dummy translations for checking that everything is ready for translation from your "Manage game" page.
[x] A prefix for example a trigram for your game that you prepend to all the css classes to avoid namespace conflicts, i.e. vla_selected vs selected - WONTFIX: classes are not prefixed, no conflicts seen, full rename not worth it
[x] If you are looking for advice on design and some 3rd party testing you can post a message on the developers forum, and ask other developers, there are a lot of people who will gladly do it.
## ''' Special testing '''
[x] Click "Use minified JS" and "Use minified CSS" buttons on the game management page, then test your game.  This will prevent you from having a panic attack when the game releases in alpha and it is stuck in "Connecting to game".
[x] Game is tested with spectator (non player observer): click the red arrow next to Test spectator, under player panels. As a spectator, you should be able to see the game as if you were sitting beside of the players at a real table: all public information, no private information.
[x] Game is tested with in-game replay from last move feature (by clicking on notification log items).
[x] After finishing a game, it is possible to watch the replay (using the "Replay game" button on the table page) from game start to game end without errors.
[x] Game works in Chrome and Firefox browsers at least. Also very recommended to test in Edge and Safari.
[x] Game works on mobile device (if you don't have mobile device to test at least test in Chrome with smaller screen, they have a mode for that)
[x] Test your game in realtime mode. Usually people will run out of time if you use default times unless you add call giveExtraTime($active_player_id) before each turn
[x] Check your game against the waiting screen, otherwise game start can fail. See [[Practical_debugging##Debugging_an_issue_with_the_waiting_screen]]
## ''' Cleanup '''
[x] Remove all unnecessary console.log and other tracing from your js code (Note: technically it is removed by minimizer, but not console.error)
[x] Remove all unnecessary debug logging from your php code
[x] Copyright headers in all source files have your name
[x] Remove unnecessary files from main folder (can move some to misc folder)
[x] If using TypeScript and Scss - move them to "src" folder from "modules", if not there already (modules deployed to prod, only transposed files are needed)
## ''' Static Analysis '''
[x] Some of the checks above are automated, to run them go to control panel, go to your project and select "Dry run build"
[x] There is also "Check project" button (it will open like a game table, and you can type your project and click on Start button to run analysis) - but it may be obsolete
