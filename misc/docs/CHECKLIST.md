# Pre-release checklist
## ''' License '''
[  ] BGA must have a license for the game for a project to be moved to production, even to alpha. If you don't have license yet you can continue checking other stuff from the list below, but at the end it cannot be moved until license situation is cleared.
## ''' Metadata and graphics '''
[  ] [[Game_meta-information: gameinfos.inc.php]] has correct and up to date information about the game.
[  ] Game box graphics is 3D version of the game box (if available) and publisher icon is correct (see [[Game art: img directory]]). Space around the box has to be transparent, not white.
[  ] You have added the requested images in the Game Metadata Manager to make the game page pretty
[  ] There are no images in the img directory that are not needed anymore
[  ] Multiple images (i.e. cards) are compressed in "Sprite" (see [[Game art: img directory]])
[  ] Each image should not exceed 4Mb
[  ] Total size should not exceed 15Mb, image compression should be used otherwise (it also helps a lot to re-encode images as indexed palette vs RBG). If you have legitimate reason to have more than 15Mb (i.e. expansions), you have to make a note of that when requesting move to alpha
[  ] If you use extra fonts, they should be freeware (please include a .txt with the licence information in addition to the font files)
## ''' Server side '''
[  ] When giving their turn to a player, you give them some extra time with the giveExtraTime() function
[  ] Game progression is implemented (getGameProgression() in php)
[  ] Zombie turn is implemented (zombieTurn() in php). For more details about how the Zombie code should work and be tested, see [[Zombie Mode|Zombie mode]].
[  ] You have defined and implemented some meaningful statistics for your game (i.e. total points, point from source A, B, C...)
[  ] Game has meaningful notification messages (but don't overkill it, more user logs will slow down the loading)
[  ] You implemented tiebreaking (using aux score field) and updated tiebreaker description in meta-data
[  ] Database: make sure you do not programmatically manage transactions or call queries that change database schema or even TRUNCATE (queries that would cause implicit commit) during normal game opearations
[  ] Database: make sure you DB schema would be sufficient to complete the game and good enough for possible expantions, changing db schema schema after the release even in alpha is very challenging (it is possible but much better if you don't need to deal with it)
## ''' Client side '''
[  ] Check that you use ajaxcall/bgaPerformAction only on player actions and never initiated programmatically. Otherwise, your code will very likely create race conditions resulting in deadlocks or other errors. This can also break replays and tutorials. ''Exception: sometimes you can do no-op moves with timeouts (i.e. user has only one choice, but its unwise to reveal this information by skipping user turn), timeout has to be canceling itself if state transition happen automaticaly (i.e. during replay)''
## ''' User Interface '''
[  ] Review BGA UI design Guidelines [[BGA_Studio_Guidelines]]
[  ] Check all your English messages for proper use of punctuation, capitalization, usage of present tense in notification (not past) and gender neutrality. See [[Translations]] for English rules.
[  ] If the elements in your game zone don't occupy all the available horizontal space, '''they should be centered'''.
[  ] If your game elements become blurry or pixellated when using the browser zoom, you may want to consider [[Game_art:_img_directory##Use_background-size | higher resolution images with background-size]]
[  ] Non-self explanatory graphic elements should have tooltips
[  ] Strings in your source code are ready for translation. See [[Translations]]. You can generate dummy translations for checking that everything is ready for translation from your "Manage game" page.
[  ] A prefix for example a trigram for your game that you prepend to all the css classes to avoid namespace conflicts, i.e. vla_selected vs selected
[  ] If you are looking for advice on design and some 3rd party testing you can post a message on the developers forum, and ask other developers, there are a lot of people who will gladly do it.
## ''' Special testing '''
[  ] Click "Use minified JS" and "Use minified CSS" buttons on the game management page, then test your game.  This will prevent you from having a panic attack when the game releases in alpha and it is stuck in "Connecting to game".
[  ] Game is tested with spectator (non player observer): click the red arrow next to Test spectator, under player panels. As a spectator, you should be able to see the game as if you were sitting beside of the players at a real table: all public information, no private information.
[  ] Game is tested with in-game replay from last move feature (by clicking on notification log items).
[  ] After finishing a game, it is possible to watch the replay (using the "Replay game" button on the table page) from game start to game end without errors.
[  ] Game works in Chrome and Firefox browsers at least. Also very recommended to test in Edge and Safari.
[  ] Game works on mobile device (if you don't have mobile device to test at least test in Chrome with smaller screen, they have a mode for that)
[  ] Test your game in realtime mode. Usually people will run out of time if you use default times unless you add call giveExtraTime($active_player_id) before each turn
[  ] Check your game against the waiting screen, otherwise game start can fail. See [[Practical_debugging##Debugging_an_issue_with_the_waiting_screen]]
## ''' Cleanup '''
[  ] Remove all unnecessary console.log and other tracing from your js code (Note: technically it is removed by minimizer, but not console.error)
[  ] Remove all unnecessary debug logging from your php code
[  ] Copyright headers in all source files have your name
[  ] Remove unnecessary files from main folder (can move some to misc folder)
[  ] If using TypeScript and Scss - move them to "src" folder from "modules", if not there already (modules deployed to prod, only transposed files are needed)
## ''' Static Analysis '''
[  ] Some of the checks above are automated, to run them go to control panel, go to your project and select "Dry run build"
[  ] There is also "Check project" button (it will open like a game table, and you can type your project and click on Start button to run analysis) - but it may be obsolete

## ''' Finally move to Alpha status '''
[  ] We want the correct formal name of the game to be used for the project (i.e. '''agricola''', not '''myagricolaproject''').   If you DO NOT have the correct formal name of the game for your project, then 
[  ]## If possible (meaning if there is not already a project with that name) copy your project to a new project '''matching exactly the name of the game''' (no prefix or suffix). If not possible move on to the next steps, admin will have to retrieve the other project and overwrite it. In this case, after you request the alpha deployment, please reply to the automatic reply with the correct name.
[  ] If you DO have the correct formal name of the game for your project, or completed the above steps, create a build for your game from the "manage game" page (using the '''Build a new release version''' section) and check the log to make sure that everything builds fine (after a successful build, you should see a new version in "Versions available for production").
[  ] Are you really ready? Click on the '''Request ALPHA status''' button available above your just built version. You cannot deploy yourself from the "manage game" page until this first ALPHA deploy has been done by the admins. Clicking this button will file a request in the BGA ticketing system and you'll receive a confirmation email allowing to reply to add some information on the ticket if needed. Note: you can also ask for moving your project to "private Alpha" - in this case you will invite people manually rather than having your game immediately visible to the whole BGA reviewers community. When you press you button you should receive acknowledgement email, you can reply to this email with more details if needed.
