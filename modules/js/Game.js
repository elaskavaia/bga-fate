/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * GalacticCruise implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */
class Game0Basics {
    // proxies for GameGui properties/methods accessed via gameui
    get player_id() {
        return gameui.player_id;
    }
    format_string_recursive(log, args) {
        return gameui.format_string_recursive(log, args);
    }
    addTooltipHtml(nodeId, html, delay) {
        gameui.addTooltipHtml(nodeId, html, delay);
    }
    bgaAnimationsActive() {
        return gameui.bgaAnimationsActive();
    }
    constructor(bga) {
        this.defaultTooltipDelay = 800;
        this.lastMoveId = 0;
        this.prevLogId = 0;
        console.log("game constructor");
        this.bga = bga;
    }
    setup(gamedatas) {
        this.gamedatas = gamedatas;
        console.log("Starting game setup", gamedatas);
        const first_player_id = Object.keys(gamedatas.players)[0];
        if (!this.bga.players.isCurrentPlayerSpectator())
            this.player_color = gamedatas.players[this.player_id].color;
        else
            this.player_color = gamedatas.players[first_player_id].color;
    }
    // utils
    /**
     * Remove all listed class from all document elements
     * @param classList - list of classes  array
     */
    removeAllClasses(...classList) {
        if (!classList)
            return;
        classList.forEach((className) => {
            document.querySelectorAll(`.${className}`).forEach((node) => {
                node.classList.remove(className);
            });
        });
    }
    onCancel(event) {
        this.cancelLocalStateEffects();
    }
    cancelLocalStateEffects() {
        //console.log(this.last_server_state);
        if (gameui.on_client_state)
            gameui.restoreServerGameState();
        gameui.updatePageTitle();
    }
    destroyDivOtherCopies(id) {
        const panels = document.querySelectorAll("#" + id);
        panels.forEach((p, i) => {
            if (i < panels.length - 1)
                p.parentNode.removeChild(p);
        });
        return panels[0] ?? null;
    }
    setupLocalControls(divId) {
        // undo adds more of these
        this.destroyDivOtherCopies(divId);
        if (this.bga.players.isCurrentPlayerSpectator()) {
            const loc = document.querySelector("#right-side .spectator-mode");
            if (loc)
                loc.insertAdjacentElement("beforeend", $(divId));
        }
        else {
            const loc = document.querySelector("#current_player_board");
            if (loc)
                loc.insertAdjacentElement("beforeend", $(divId));
        }
    }
    addCancelButton(name, handler) {
        if (!name)
            name = _("Cancel");
        if (!handler)
            handler = () => this.onCancel();
        if ($("button_cancel"))
            $("button_cancel").remove();
        this.bga.statusBar.addActionButton(name, handler, { id: "button_cancel", color: "alert" });
    }
    /** Show pop in dialog. If you need div id of dialog its `popin_${id}` where id is second parameter here */
    showPopin(html, id = "gg_dialog", title = undefined, refresh = false) {
        const content_id = `popin_${id}_contents`;
        if (refresh && $(content_id)) {
            $(content_id).innerHTML = html;
            return undefined;
        }
        const dialog = new ebg.popindialog();
        dialog.create(id);
        if (title)
            dialog.setTitle(title);
        dialog.setContent(html);
        dialog.show();
        return dialog;
    }
    getStateName() {
        return this.gamedatas.gamestate.name;
    }
    getPlayerColor(playerId) {
        return this.gamedatas.players[playerId]?.color ?? "ffffff";
    }
    getPlayerName(playerId) {
        return this.gamedatas.players[playerId]?.name ?? _("Not a Player");
    }
    custom_getPlayerIdByColor(color) {
        for (var playerId in this.gamedatas.players) {
            var playerInfo = this.gamedatas.players[playerId];
            if (color == playerInfo.color) {
                return parseInt(playerId);
            }
        }
        return undefined;
    }
    removeTooltip(nodeId) {
        // if (this.tooltips[nodeId])
        if (!nodeId)
            return;
        //console.log("removeTooltip", nodeId);
        $(nodeId)?.classList.remove("withtooltip");
        gameui.removeTooltip(nodeId);
        delete gameui.tooltips[nodeId]; // HACK: removeTooltip leaking this entry, removing manually
    }
    callfn(methodName, ...args) {
        if (this[methodName] !== undefined) {
            console.log("Calling " + methodName, args);
            return this[methodName](...args);
        }
        return undefined;
    }
    /** @Override onScriptError from gameui */
    onScriptError(msg, url, linenumber) {
        if (gameui.page_is_unloading) {
            // Don't report errors during page unloading
            return;
        }
        console.error(msg);
    }
    divYou() {
        var color = "black";
        var color_bg = "";
        if (this.gamedatas.players[this.player_id]) {
            color = this.gamedatas.players[this.player_id].color;
        }
        if (this.gamedatas.players[this.player_id] && this.gamedatas.players[this.player_id].color_back) {
            color_bg = "background-color:#" + this.gamedatas.players[this.player_id].color_back + ";";
        }
        var you = '<span style="font-weight:bold;color:#' + color + ";" + color_bg + '">' + _("You") + "</span>";
        return you;
    }
    cloneAndFixIds(orig, postfix, removeInlineStyle) {
        if (!$(orig)) {
            const div = document.createElement("div");
            div.innerHTML = _("NOT FOUND") + " " + orig.toString();
            return div;
        }
        const fixIds = function (node) {
            if (node.id) {
                node.id = node.id + postfix;
            }
            if (removeInlineStyle) {
                node.removeAttribute("style");
            }
        };
        const div = $(orig).cloneNode(true);
        div.querySelectorAll("*").forEach(fixIds);
        fixIds(div);
        return div;
    }
    getTr(name, args = {}) {
        if (!name)
            return "";
        if (name.log !== undefined) {
            const notif = name;
            const log = this.format_string_recursive(gameui.clienttranslate_string(notif.log), notif.args);
            return log;
        }
        if (typeof name !== "string")
            return name.toString();
        //if (name.includes("$"))
        {
            const log = this.format_string_recursive(gameui.clienttranslate_string(name), args);
            return log;
        }
        //return this.clienttranslate_string(name);
    }
    reloadCss() {
        var links = document.getElementsByTagName("link");
        for (var cl in links) {
            var link = links[cl];
            if (link.rel === "stylesheet" && link.href.includes("99999")) {
                var index = link.href.indexOf("?timestamp=");
                var href = link.href;
                if (index >= 0) {
                    href = href.substring(0, index);
                }
                link.href = href + "?timestamp=" + Date.now();
                console.log("reloading " + link.href);
            }
        }
    }
    isSolo() {
        return this.gamedatas.playerorder.length == 1;
    }
    addTooltipToLogItems(log_id) {
        // override
    }
    addMoveToLog(log_id, move_id) {
        if (move_id)
            this.lastMoveId = move_id;
        if (this.prevLogId + 1 < log_id) {
            // we skip over some logs, but we need to look at them also
            for (let i = this.prevLogId + 1; i < log_id; i++) {
                this.addTooltipToLogItems(i);
            }
        }
        this.addTooltipToLogItems(log_id);
        // add move #
        var prevmove = document.querySelector('[data-move-id="' + move_id + '"]');
        if (prevmove) {
            // ?
        }
        else if (move_id) {
            const tsnode = document.createElement("div");
            tsnode.classList.add("movestamp");
            tsnode.innerHTML = _("Move #") + move_id;
            const lognode = $("log_" + log_id);
            lognode.appendChild(tsnode);
            tsnode.setAttribute("data-move-id", String(move_id));
        }
        this.prevLogId = log_id;
    }
    notif_log(args) {
        // if (notif.log) {
        //   console.log(notif.log, notif.args);
        //   var message = this.format_string_recursive(notif.log, notif.args);
        //   if (message != notif.log) console.log(message);
        // } else {
        if (args.log) {
            var message = this.format_string_recursive(args.log, args.args);
            delete args.log;
            console.log("debug log", message, args);
        }
        else {
            console.log("hidden log", args);
        }
    }
    notif_message_warning(notif) {
        if (gameui.bgaAnimationsActive()) {
            var message = this.format_string_recursive(notif.log, notif.args);
            this.bga.dialogs.showMessage(_("Warning:") + " " + message, "info");
        }
    }
    notif_message_info(notif) {
        if (gameui.bgaAnimationsActive()) {
            var message = this.format_string_recursive(notif.log, notif.args);
            this.bga.dialogs.showMessage(_("Announcement:") + " " + message, "info");
        }
    }
}
/** This is essentically dojo.place but without dojo */
function placeHtml(html, parent, how = "beforeend") {
    $(parent).insertAdjacentHTML(how, html);
}
function getIntPart(word, i) {
    return parseInt(getPart(word, i));
}
function getPart(word, i) {
    var arr = word.split("_");
    if (i < 0)
        i = arr.length + i;
    if (arr.length <= i)
        return "";
    return arr[i];
}
function getFirstParts(word, count) {
    var arr = word.split("_");
    var res = arr[0];
    for (var i = 1; i < arr.length && i < count; i++) {
        res += "_" + arr[i];
    }
    return res;
}
function getParentParts(word) {
    var arr = word.split("_");
    if (arr.length <= 1)
        return "";
    return getFirstParts(word, arr.length - 1);
}

class LaAnimations {
    constructor() {
        this.defaultAnimationDuration = 500;
    }
    phantomMove(mobileId, newparentId, duration, mobileStyle, onEnd) {
        var mobileNode = $(mobileId);
        if (!mobileNode)
            throw new Error(`Does not exists ${mobileId}`);
        var newparent = $(newparentId);
        if (!newparent)
            throw new Error(`Does not exists ${newparentId}`);
        if (duration === undefined)
            duration = this.defaultAnimationDuration;
        if (!duration || duration < 0)
            duration = 0;
        const noanimation = duration <= 0 || !mobileNode.parentNode;
        const oldParent = mobileNode.parentElement;
        var clone = null;
        if (!noanimation) {
            // do animation
            clone = this.projectOnto(mobileNode, "_temp");
            mobileNode.style.opacity = "0"; // hide original
        }
        const rel = mobileStyle?.relation;
        if (rel) {
            delete mobileStyle.relation;
        }
        if (rel == "first") {
            newparent.insertBefore(mobileNode, null);
        }
        else {
            newparent.appendChild(mobileNode); // move original
        }
        setStyleAttributes(mobileNode, mobileStyle);
        newparent.classList.add("move_target");
        oldParent?.classList.add("move_source");
        mobileNode.offsetHeight; // recalc
        if (noanimation) {
            setTimeout(() => {
                newparent.offsetHeight;
                newparent.classList.remove("move_target");
                oldParent?.classList.remove("move_source");
                if (onEnd)
                    onEnd(mobileNode);
            }, 0);
            return;
        }
        var desti = this.projectOnto(mobileNode, "_temp2"); // invisible destination on top of new parent
        try {
            //setStyleAttributes(desti, mobileStyle);
            clone.style.transitionDuration = duration + "ms";
            clone.style.transitionProperty = "all";
            clone.style.visibility = "visible";
            clone.style.opacity = "1";
            // that will cause animation
            clone.style.left = desti.style.left;
            clone.style.top = desti.style.top;
            clone.style.transform = desti.style.transform;
            // now we don't need destination anymore
            desti.parentNode?.removeChild(desti);
            setTimeout(() => {
                newparent.classList.remove("move_target");
                oldParent?.classList.remove("move_source");
                mobileNode.style.removeProperty("opacity"); // restore visibility of original
                clone.parentNode?.removeChild(clone); // destroy clone
                if (onEnd)
                    onEnd(mobileNode);
            }, duration);
        }
        catch (e) {
            // if bad thing happen we have to clean up clones
            console.error("ERR:C01:animation error", e);
            desti.parentNode?.removeChild(desti);
            clone.parentNode?.removeChild(clone); // destroy clone
            //if (onEnd) onEnd(mobileNode);
        }
    }
    getFulltransformMatrix(from, to) {
        let fullmatrix = "";
        let par = from;
        while (par != to && par != null && par != document.body) {
            var style = window.getComputedStyle(par);
            var matrix = style.transform; //|| "matrix(1,0,0,1,0,0)";
            if (matrix && matrix != "none")
                fullmatrix += " " + matrix;
            par = par.parentNode;
            // console.log("tranform  ",fullmatrix,par);
        }
        return fullmatrix;
    }
    projectOnto(from, postfix, ontoWhat) {
        const elem = $(from);
        let over;
        if (ontoWhat)
            over = $(ontoWhat);
        else
            over = $("oversurface"); // this div has to exists with pointer-events: none and cover all area with high zIndex
        var elemRect = elem.getBoundingClientRect();
        //console.log("elemRect", elemRect);
        var newId = elem.id + postfix;
        var old = $(newId);
        if (old)
            old.parentNode.removeChild(old);
        var clone = elem.cloneNode(true);
        clone.id = newId;
        clone.classList.add("phantom");
        clone.classList.add("phantom" + postfix);
        clone.style.transitionDuration = "0ms"; // disable animation during projection
        var fullmatrix = this.getFulltransformMatrix(elem.parentNode, over.parentNode);
        // Calculate the scale factor of oversurface relative to viewport
        // This handles cases where oversurface or its ancestors are scaled
        const overElement = over;
        const overRect = over.getBoundingClientRect();
        const scaleX = overElement.offsetWidth > 0 ? overRect.width / overElement.offsetWidth : 1;
        const scaleY = overElement.offsetHeight > 0 ? overRect.height / overElement.offsetHeight : 1;
        // Set dimensions adjusted for scale so clone appears same visual size as original
        if (elemRect.width > 1) {
            clone.style.width = elemRect.width / scaleX + "px";
            clone.style.height = elemRect.height / scaleY + "px";
        }
        // Set initial position before appending so we measure from a known baseline
        clone.style.position = "absolute";
        clone.style.left = "0px";
        clone.style.top = "0px";
        over.appendChild(clone);
        var cloneRect = clone.getBoundingClientRect();
        const centerY = elemRect.y + elemRect.height / 2;
        const centerX = elemRect.x + elemRect.width / 2;
        // centerX/Y is where the center point must be
        // I need to calculate the offset from top and left
        // Therefore I remove half of the dimensions + the existing offset
        const offsetX = centerX - cloneRect.width / 2 - cloneRect.x;
        const offsetY = centerY - cloneRect.height / 2 - cloneRect.y;
        // Then remove the clone's parent position (since left/top is from the parent)
        // Divide by scale factor to convert from viewport pixels to CSS pixels
        clone.style.left = offsetX / scaleX + "px";
        clone.style.top = offsetY / scaleY + "px";
        clone.style.transform = fullmatrix;
        clone.style.transitionDuration = undefined;
        return clone;
    }
    cardFlip(mobileId, newState, duration, onEnd) {
        var mobileNode = $(mobileId);
        if (!mobileNode)
            throw new Error(`Does not exists ${mobileId}`);
        if (duration === undefined)
            duration = this.defaultAnimationDuration;
        if (!duration || duration < 0)
            duration = 0;
        const noanimation = duration <= 0 || !mobileNode.parentNode;
        if (noanimation) {
            mobileNode.dataset.state = newState;
            setTimeout(() => {
                if (onEnd)
                    onEnd(mobileNode);
            }, 0);
            return;
        }
        const clone = this.projectOnto(mobileNode, "_temp");
        clone.innerHTML = "";
        mobileNode.dataset.state = newState;
        mobileNode.offsetHeight; // recalc
        const desti = this.projectOnto(mobileNode, "_temp2"); // invisible destination on top of new parent
        desti.innerHTML = "";
        mobileNode.style.opacity = "0"; // hide original
        placeHtml(`<div id="card_temp"></div>`, "oversurface");
        const group = $("card_temp");
        group.style.left = clone.style.left;
        group.style.top = clone.style.top;
        group.style.transform = clone.style.transform;
        group.style.width = clone.style.width;
        group.style.height = clone.style.height;
        group.style.position = "absolute";
        group.style.transformStyle = "preserve-3d";
        group.style.transitionProperty = "all";
        group.appendChild(clone);
        group.appendChild(desti);
        delete clone.style.left;
        delete clone.style.top;
        delete desti.style.left;
        delete desti.style.top;
        desti.style.transform = "rotateY(180deg)";
        desti.style.backfaceVisibility = "hidden";
        clone.style.backfaceVisibility = "hidden";
        try {
            //setStyleAttributes(desti, mobileStyle);
            group.style.transitionDuration = duration + "ms";
            //group.style.visibility = "visible";
            //group.style.opacity = "1";
            // that will cause animation
            //group.style.scale = "2.0";
            group.style.animation = `flip ${duration}ms`;
            setTimeout(() => {
                mobileNode.style.removeProperty("opacity"); // restore visibility of original
                group.remove();
                if (onEnd)
                    onEnd(mobileNode);
            }, duration);
        }
        catch (e) {
            // if bad thing happen we have to clean up clones
            console.error("ERR:C01:animation error", e);
            group.remove();
            if (onEnd)
                onEnd(mobileNode);
        }
    }
}
function setStyleAttributes(element, attrs) {
    if (attrs !== undefined) {
        Object.keys(attrs).forEach((key) => {
            element.style.setProperty(key, attrs[key]);
        });
    }
}

const BgaAnimations = (await globalThis.importEsmLib("bga-animations", "1.x"));

class Game1Tokens extends Game0Basics {
    constructor() {
        super(...arguments);
        this.globlog = 1;
        this.tokenInfoCache = {};
        this.defaultAnimationDuration = 500;
        this.classActiveSlot = "active_slot";
        this.classActiveSlotHidden = "hidden_active_slot";
        this.classButtonDisabled = "disabled";
        this.classSelected = "gg_selected"; // for the purpose of multi-select operations
        this.classSelectedAlt = "gg_selected_alt"; // for the purpose of multi-select operations with alternative node
        this.game = this;
    }
    setupGame(gamedatas) {
        this.tokenInfoCache = {};
        // create the animation manager, and bind it to the `game.bgaAnimationsActive()` function
        this.animationManager = new BgaAnimations.Manager({
            animationsActive: () => this.bgaAnimationsActive()
        });
        this.animationLa = new LaAnimations();
        if (!this.gamedatas.tokens) {
            console.error("Missing gamadatas.tokens!");
            this.gamedatas.tokens = {};
        }
        if (!this.gamedatas.token_types) {
            console.error("Missing gamadatas.token_types!");
            this.gamedatas.token_types = {};
        }
        this.gamedatas.tokens["limbo"] = {
            key: "limbo",
            state: 0,
            location: "thething"
        };
        this.placeTokenSetup("limbo");
        placeHtml(`<div id="oversurface"></div>`, this.bga.gameArea.getElement());
        this.setupTokens();
        this.updateCountersSafe(this.gamedatas.counters);
    }
    onLeavingState(stateName, args) {
        console.log("onLeavingState: " + stateName);
        //this.disconnectAllTemp();
        this.removeAllClasses(this.classActiveSlot, this.classActiveSlotHidden);
        if (!gameui.on_client_state) {
            this.removeAllClasses(this.classSelected, this.classSelectedAlt);
        }
        //super.onLeavingState(stateName);
    }
    cancelLocalStateEffects() {
        //console.log(this.last_server_state);
        this.game.removeAllClasses(this.classActiveSlot, this.classActiveSlotHidden);
        this.game.removeAllClasses(this.classSelected, this.classSelectedAlt);
        //this.restoreServerData();
        //this.updateCountersSafe(this.gamedatas.counters);
    }
    addShowMeButton(scroll) {
        const firstTarget = document.querySelector("." + this.classActiveSlot);
        if (!firstTarget)
            return;
        this.bga.statusBar.addActionButton(_("Show me"), () => {
            const butt = $("button_showme");
            const firstTarget = document.querySelector("." + this.classActiveSlot);
            if (!firstTarget)
                return;
            if (scroll)
                $(firstTarget).scrollIntoView({ behavior: "smooth", block: "center" });
            document.querySelectorAll("." + this.classActiveSlot).forEach((node) => {
                const elem = node;
                elem.style.removeProperty("animation");
                elem.style.setProperty("animation", "active-pulse 500ms 3");
                butt.classList.add(this.classButtonDisabled);
                setTimeout(() => {
                    elem.style.removeProperty("animation");
                    butt.classList.remove(this.classButtonDisabled);
                }, 1500);
            });
        }, {
            color: "secondary",
            id: "button_showme"
        });
    }
    getAllLocations() {
        const res = [];
        for (const key in this.gamedatas.token_types) {
            const info = this.gamedatas.token_types[key];
            if (this.isLocationByType(key) && info.scope != "player")
                res.push(key);
        }
        for (var token in this.gamedatas.tokens) {
            var tokenInfo = this.gamedatas.tokens[token];
            var location = tokenInfo.location;
            if (location && res.indexOf(location) < 0)
                res.push(location);
        }
        return res;
    }
    isLocationByType(id) {
        return this.getRulesFor(id, "type", "").indexOf("location") >= 0;
    }
    updateCountersSafe(counters) {
        //console.log(counters);
        for (var key in counters) {
            let node = $(key);
            if (counters.hasOwnProperty(key)) {
                if (!node) {
                    const deckId = key.replace("counter_", "");
                    if ($(deckId)) {
                        placeHtml(`<div id='${key}' class='counter'></div>`, deckId);
                        node = $(key);
                    }
                }
                if (node) {
                    const value = counters[key].value;
                    node.dataset.state = value;
                }
                else {
                    console.log("unknown counter " + key);
                }
            }
        }
    }
    setupTokens() {
        console.log("Setup tokens");
        for (let loc of this.getAllLocations()) {
            this.placeTokenSetup(loc);
        }
        for (let token in this.gamedatas.tokens) {
            const tokenInfo = this.gamedatas.tokens[token];
            const location = tokenInfo.location;
            if (location && !this.gamedatas.tokens[location] && !$(location)) {
                this.placeTokenSetup(location);
            }
            this.placeTokenSetup(token);
        }
        for (let token in this.gamedatas.tokens) {
            this.updateTooltip(token);
        }
        for (let loc of this.getAllLocations()) {
            this.updateTooltip(loc);
        }
    }
    setTokenInfo(token_id, place_id, new_state, serverdata, args) {
        var token = token_id;
        if (!this.gamedatas.tokens[token]) {
            this.gamedatas.tokens[token] = {
                key: token,
                state: 0,
                location: "limbo"
            };
        }
        if (args) {
            args._prev = structuredClone(this.gamedatas.tokens[token]);
        }
        if (place_id !== undefined) {
            this.gamedatas.tokens[token].location = place_id;
        }
        if (new_state !== undefined) {
            this.gamedatas.tokens[token].state = new_state;
        }
        //if (serverdata === undefined) serverdata = true;
        //if (serverdata && this.gamedatas_server) this.gamedatas_server.tokens[token] = dojo.clone(this.gamedatas.tokens[token]);
        return this.gamedatas.tokens[token];
    }
    createToken(placeInfo) {
        const tokenId = placeInfo.key;
        const location = placeInfo.place_from ?? placeInfo.location ?? this.getRulesFor(tokenId, "location");
        const div = document.createElement("div");
        div.id = tokenId;
        let parentNode = $(location);
        if (location && !parentNode) {
            if (location.indexOf("{") == -1)
                console.error("Cannot find location [" + location + "] for ", div);
            parentNode = $("limbo");
        }
        parentNode.appendChild(div);
        return div;
    }
    updateToken(tokenNode, placeInfo) {
        const tokenId = placeInfo.key;
        const displayInfo = this.getTokenDisplayInfo(tokenId);
        const classes = displayInfo.imageTypes.split(/  */);
        tokenNode.classList.add(...classes);
        if (displayInfo.name)
            tokenNode.dataset.name = this.getTr(displayInfo.name);
        this.addListenerWithGuard(tokenNode, placeInfo.onClick);
    }
    addListenerWithGuard(tokenNode, handler) {
        if (!tokenNode.getAttribute("_lis") && handler) {
            tokenNode.addEventListener("click", handler);
            tokenNode.setAttribute("_lis", "1");
        }
    }
    findActiveParent(element) {
        if (this.isActiveSlot(element))
            return element;
        const parent = element.parentElement;
        if (!parent || parent.id == "thething" || parent == element)
            return null;
        return this.findActiveParent(parent);
    }
    /**
     * This is convenient function to be called when processing click events, it - remembers id of object - stops propagation - logs to
     * console - the if checkActive is set to true check if element has active_slot class
     */
    onClickSanity(event, checkActiveSlot, checkActivePlayer) {
        let id = event.currentTarget.id;
        let target = event.target;
        if (id == "thething") {
            let node = this.findActiveParent(target);
            id = node?.id;
            target = node;
        }
        console.log("on slot " + id, target?.id || target);
        if (!id)
            return null;
        if (this.showHelp(id))
            return null;
        if (checkActiveSlot && !id.startsWith("button_") && !this.checkActiveSlot(id)) {
            return null;
        }
        if (checkActivePlayer && !this.checkActivePlayer()) {
            return null;
        }
        if (target.dataset.targetId)
            return target.dataset.targetId;
        id = id.replace("tmp_", "");
        id = id.replace("button_", "");
        return id;
    }
    // override to hook the help
    showHelp(id) {
        return false;
    }
    // override to prove additinal animation parameters
    getPlaceRedirect(tokenInfo, args = {}) {
        return tokenInfo;
    }
    checkActivePlayer() {
        if (!this.bga.players.isCurrentPlayerActive()) {
            this.bga.dialogs.showMessage(_("This is not your turn"), "error");
            return false;
        }
        return true;
    }
    isActiveSlot(id) {
        const node = $(id);
        if (node.classList.contains(this.classActiveSlot)) {
            return true;
        }
        if (node.classList.contains(this.classActiveSlotHidden)) {
            return true;
        }
        return false;
    }
    checkActiveSlot(id, showError = true) {
        if (!this.isActiveSlot(id)) {
            if (showError) {
                console.error(new Error("unauth"), id);
                this.bga.dialogs.showMoveUnauthorized();
            }
            return false;
        }
        return true;
    }
    async placeTokenServer(tokenId, location, state, args) {
        const tokenInfo = this.setTokenInfo(tokenId, location, state, true, args);
        await this.placeToken(tokenId, tokenInfo, args);
        this.updateTooltip(tokenId);
        this.updateTooltip(tokenInfo.location);
    }
    prapareToken(tokenId, tokenDbInfo, args = {}) {
        if (!tokenDbInfo) {
            tokenDbInfo = this.gamedatas.tokens[tokenId];
        }
        if (!tokenDbInfo) {
            let tokenNode = $(tokenId);
            if (tokenNode) {
                const st = parseInt(tokenNode.dataset.state);
                tokenDbInfo = this.setTokenInfo(tokenId, tokenNode.parentElement.id, st, false);
            }
            else {
                //console.error("Cannot setup token for " + tokenId);
                tokenDbInfo = this.setTokenInfo(tokenId, undefined, 0, false);
            }
        }
        const placeInfo = this.getPlaceRedirect(tokenDbInfo, args);
        const tokenNode = $(tokenId) ?? this.createToken(placeInfo);
        tokenNode.dataset.state = String(tokenDbInfo.state);
        tokenNode.dataset.location = tokenDbInfo.location;
        this.updateToken(tokenNode, placeInfo);
        // no movement
        if (placeInfo.nop) {
            return placeInfo;
        }
        const location = placeInfo.location;
        if (!$(location)) {
            if (location)
                console.error(`Unknown place ${location} for ${tokenId}`);
            return undefined;
        }
        return placeInfo;
    }
    placeTokenSetup(tokenId, tokenDbInfo) {
        const placeInfo = this.prapareToken(tokenId, tokenDbInfo);
        if (!placeInfo) {
            return;
        }
        const tokenNode = $(tokenId);
        if (!tokenNode)
            return;
        void placeInfo.onStart?.(tokenNode);
        if (placeInfo.nop) {
            return;
        }
        $(placeInfo.location).appendChild(tokenNode);
        void placeInfo.onEnd?.(tokenNode);
    }
    async placeToken(tokenId, tokenDbInfo, args = {}) {
        try {
            const placeInfo = this.prapareToken(tokenId, tokenDbInfo, args);
            if (!placeInfo) {
                return;
            }
            const tokenNode = $(tokenId);
            let animTime = placeInfo.animtime ?? this.defaultAnimationDuration;
            if (this.game.bgaAnimationsActive() == false || args.noa || placeInfo.animtime === 0 || !tokenNode.parentNode) {
                animTime = 0;
            }
            if (placeInfo.onStart)
                await placeInfo.onStart(tokenNode);
            if (!placeInfo.nop)
                await this.slideAndPlace(tokenNode, placeInfo.location, animTime, 0, undefined, placeInfo.onEnd);
            else
                placeInfo.onEnd?.(tokenNode);
            //if (animTime == 0) $(location).appendChild(tokenNode);
            //else void this.animationManager.slideAndAttach(tokenNode, $(location));
        }
        catch (e) {
            console.error("Exception thrown", e, e.stack);
            // this.showMessage(token + " -> FAILED -> " + place + "\n" + e, "error");
        }
    }
    updateTooltip(tokenId, attachTo, delay) {
        if (attachTo === undefined) {
            attachTo = tokenId;
        }
        let attachNode = $(attachTo);
        if (!attachNode)
            return;
        // attach node has to have id
        if (!attachNode.id)
            attachNode.id = "gen_id_" + Math.random() * 10000000;
        // console.log("tooltips for "+token);
        if (typeof tokenId != "string") {
            console.error("cannot calc tooltip" + tokenId);
            return;
        }
        var tokenInfo = this.getTokenDisplayInfo(tokenId);
        if (tokenInfo.name) {
            attachNode.dataset.name = this.game.getTr(tokenInfo.name);
        }
        if (tokenInfo.showtooltip == false) {
            return;
        }
        if (tokenInfo.title) {
            attachNode.setAttribute("title", this.game.getTr(tokenInfo.title));
            return;
        }
        if (!tokenInfo.tooltip && !tokenInfo.name) {
            return;
        }
        var main = this.getTooltipHtmlForTokenInfo(tokenInfo);
        if (main) {
            attachNode.classList.add("withtooltip");
            if (attachNode.id != tokenId)
                attachNode.dataset.tt = tokenId; // id of token that provides the tooltip
            //console.log("addTooltipHtml", attachNode.id);
            this.game.addTooltipHtml(attachNode.id, main, delay ?? this.game.defaultTooltipDelay);
            attachNode.removeAttribute("title"); // unset title so both title and tooltip do not show up
            this.handleStackedTooltips(attachNode);
        }
        else {
            attachNode.classList.remove("withtooltip");
        }
    }
    handleStackedTooltips(attachNode) { }
    getTooltipHtmlForToken(token) {
        if (typeof token != "string") {
            console.error("cannot calc tooltip" + token);
            return null;
        }
        var tokenInfo = this.getTokenDisplayInfo(token, true);
        // console.log(tokenInfo);
        if (!tokenInfo)
            return;
        return this.getTooltipHtmlForTokenInfo(tokenInfo);
    }
    getTooltipHtmlForTokenInfo(tokenInfo) {
        return this.getTooltipHtml(tokenInfo.name, tokenInfo.tooltip, tokenInfo.imageTypes, tokenInfo.reverseImageTypes);
    }
    getTokenName(tokenId, force = true) {
        var tokenInfo = this.getTokenDisplayInfo(tokenId);
        if (tokenInfo) {
            return this.game.getTr(tokenInfo.name);
        }
        else {
            if (!force)
                return undefined;
            return "? " + tokenId;
        }
    }
    getTooltipHtml(name, message, imgTypes = "", reverseImgTypes = "") {
        if (name == null || message == "-")
            return "";
        if (!message)
            message = "";
        var divImg = "";
        var containerType = "tooltipcontainer ";
        if (imgTypes && !imgTypes.includes("_nottimage")) {
            // Check if this is a dual-image tooltip (upgrade tiles with front and reverse)
            if (imgTypes.includes("_dual_image") && reverseImgTypes) {
                const frontImgTypes = imgTypes.replace("_dual_image", "").trim();
                divImg = `
          <div class='tooltipimage ${frontImgTypes}'></div>
          <div class='tooltipimage ${reverseImgTypes}'></div>
        `;
            }
            else {
                divImg = `<div class='tooltipimage ${imgTypes}'></div>`;
            }
            var itypes = imgTypes.split(" ");
            for (var i = 0; i < itypes.length; i++) {
                containerType += itypes[i] + "_tooltipcontainer ";
            }
        }
        const name_tr = this.game.getTr(name);
        let body = "";
        if (imgTypes.includes("_override")) {
            body = message;
        }
        else {
            const message_tr = this.game.getTr(message);
            body = `
           <div class='tooltip-left'>${divImg}</div>
           <div class='tooltip-right'>
             <div class='tooltiptitle'>${name_tr}</div>
             <div class='tooltiptext'>${message_tr}</div>
           </div>
    `;
        }
        return `<div class='${containerType}'>
        <div class='tooltip-body'>${body}</div>
    </div>`;
    }
    getTokenInfoState(tokenId) {
        var tokenInfo = this.gamedatas.tokens[tokenId];
        return Number(tokenInfo.state);
    }
    getAllRules(tokenId) {
        return this.getRulesFor(tokenId, "*", null);
    }
    getRulesFor(tokenId, field, def) {
        if (field === undefined)
            field = "r";
        var key = tokenId;
        let chain = [key];
        while (key) {
            var info = this.gamedatas.token_types[key];
            if (info === undefined) {
                key = getParentParts(key);
                if (!key) {
                    //console.error("Undefined info for " + tokenId);
                    return def;
                }
                chain.push(key);
                continue;
            }
            if (field === "*") {
                info["_chain"] = chain.join(" ");
                return info;
            }
            var rule = info[field];
            if (rule === undefined)
                return def;
            return rule;
        }
        return def;
    }
    getTokenDisplayInfo(tokenId, force = false) {
        tokenId = String(tokenId);
        const cache = this.tokenInfoCache[tokenId];
        if (!force && cache) {
            return cache;
        }
        let tokenInfo = this.getAllRules(tokenId);
        if (!tokenInfo) {
            tokenInfo = {
                key: tokenId,
                _chain: tokenId,
                name: tokenId,
                showtooltip: false
            };
        }
        else {
            tokenInfo = structuredClone(tokenInfo);
        }
        const imageTypes = tokenInfo._chain ?? tokenId ?? "";
        const ita = imageTypes.split(" ");
        const tokenKey = ita[ita.length - 1];
        const parentParts = getParentParts(tokenId);
        tokenInfo.type ?? (tokenInfo.type = this.getRulesFor(parentParts, "type", "token"));
        const declaredTypes = tokenInfo.type;
        tokenInfo.typeKey = tokenKey; // this is key in token_types structure
        tokenInfo.mainType = getPart(tokenId, 0); // first type
        tokenInfo.imageTypes = `${tokenInfo.mainType} ${declaredTypes} ${imageTypes}`.trim(); // other types used for div
        tokenInfo.location ?? (tokenInfo.location = this.getRulesFor(parentParts, "location", undefined));
        const create = tokenInfo.create ?? 0;
        if (create == 3 || create == 4) {
            const prefix = tokenKey.split("_").length;
            tokenInfo.color = getPart(tokenId, prefix);
            tokenInfo.imageTypes += " color_" + tokenInfo.color;
        }
        if (create == 3) {
            const part = getPart(tokenId, -1);
            tokenInfo.imageTypes += " n_" + part;
        }
        if (!tokenInfo.key) {
            tokenInfo.key = tokenId;
        }
        tokenInfo.tokenId = tokenId;
        try {
            this.updateTokenDisplayInfo(tokenInfo);
        }
        catch (e) {
            console.error(`Failed to update token info for ${tokenId}`, e);
        }
        this.tokenInfoCache[tokenId] = tokenInfo;
        //console.log("cached", tokenId);
        return tokenInfo;
    }
    getTokenPresentaton(type, tokenKey, args = {}) {
        if (type.includes("_div"))
            return this.createTokenImage(tokenKey);
        if (tokenKey.includes("wicon"))
            return this.createTokenImage(tokenKey);
        return this.getTokenName(tokenKey); // just a name for now
    }
    // override to generate dynamic tooltips and such
    updateTokenDisplayInfo(tokenDisplayInfo) { }
    ttSection(prefix, text) {
        if (prefix)
            return `<p><b>${prefix}</b>: ${text}</p>`;
        else
            return `<p>${text}</p>`;
    }
    createTokenImage(tokenId, state = 0) {
        const div = document.createElement("div");
        div.id = tokenId + "_tt_" + this.globlog++;
        this.updateToken(div, { key: tokenId, location: "log", state });
        div.title = this.getTokenName(tokenId, false) ?? "";
        return div.outerHTML;
    }
    isMarkedForTranslation(key, args) {
        if (!args.i18n) {
            return false;
        }
        else {
            var i = args.i18n.indexOf(key);
            if (i >= 0) {
                return true;
            }
        }
        return false;
    }
    bgaFormatText(log, args) {
        try {
            if (log && args) {
                var keys = [
                    "token_name",
                    "token2_name",
                    "token_divs",
                    "token_names",
                    "place_name",
                    "card_type_name",
                    "token_div",
                    "token2_div",
                    "token3_div",
                    "token_icon"
                ];
                for (var i in keys) {
                    const key = keys[i];
                    // console.log("checking " + key + " for " + log);
                    if (args[key] === undefined)
                        continue;
                    const arg_value = args[key];
                    if (key == "token_divs" || key == "token_names") {
                        var list = args[key].split(",");
                        var res = "";
                        for (let l = 0; l < list.length; l++) {
                            const value = list[l];
                            if (l > 0)
                                res += ", ";
                            res += this.getTokenPresentaton(key, value, args);
                        }
                        res = res.trim();
                        if (res)
                            args[key] = res;
                        continue;
                    }
                    if (typeof arg_value == "string" && this.isMarkedForTranslation(key, args)) {
                        continue;
                    }
                    var res = this.getTokenPresentaton(key, arg_value, args);
                    if (res)
                        args[key] = res;
                }
            }
        }
        catch (e) {
            console.error(log, args, "Exception thrown", e.stack);
        }
        return { log, args };
    }
    async slideAndPlace(token, finalPlace, duration, delay = 0, mobileStyle, onEnd) {
        if (!$(token))
            console.error(`token not found for ${token}`);
        if ($(token)?.parentNode == $(finalPlace))
            return;
        if (gameui.bgaAnimationsActive() == false) {
            duration = 0;
            delay = 0;
        }
        if (delay)
            await gameui.wait(delay);
        this.animationLa.phantomMove(token, finalPlace, duration, mobileStyle, onEnd);
        return gameui.wait(duration);
    }
    async notif_animate(args) {
        return gameui.wait(args.time ?? 1);
    }
    async notif_tokenMovedAsync(args) {
        void this.notif_tokenMoved(args);
    }
    async notif_tokenMoved(args) {
        if (args.list !== undefined) {
            // move bunch of tokens
            const moves = [];
            for (var i = 0; i < args.list.length; i++) {
                var one = args.list[i];
                var new_state = args.new_state;
                if (new_state === undefined) {
                    if (args.new_states !== undefined && args.new_states.length > i) {
                        new_state = args.new_states[i];
                    }
                }
                moves.push(this.placeTokenServer(one, args.place_id, new_state, args));
            }
            return Promise.all(moves);
        }
        else {
            return this.placeTokenServer(args.token_id, args.place_id, args.new_state, args);
        }
    }
    async notif_counterAsync(args) {
        void this.notif_counter(args);
    }
    /**
     *
     * name: the name of the counter
  value: the new value
  oldValue: the value before the update
  inc: the increment
  absInc: the absolute value of the increment, allowing you to use '...loses ${absInc} ...' in the notif message if you are incrementing with a negative value
  playerId (only for PlayerCounter)
  player_name (only for PlayerCounter)
     * @param args
     * @returns
     *
     */
    async notif_counter(args) {
        try {
            const name = args.name;
            const value = args.value;
            const node = $(name);
            if (node && this.gamedatas.tokens[name]) {
                args.nop = true; // no move animation
                return Promise.all([this.placeTokenServer(name, this.gamedatas.tokens[name].location, value, args), gameui.wait(500)]);
            }
            else if (node) {
                node.dataset.state = value;
            }
        }
        catch (ex) {
            console.error("Cannot update " + args.counter_name, ex, ex.stack);
        }
        return gameui.wait(500);
    }
}

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * GalacticCruise implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */
/**  Generic processing related to Operation Machine */
class GameMachine extends Game1Tokens {
    onEnteringState_PlayerTurn(opInfo) {
        if (!this.bga.players.isCurrentPlayerActive()) {
            if (opInfo?.description)
                this.bga.statusBar.setTitle(this.getTr(opInfo.description, opInfo));
            this.setSubPrompt("");
            this.addUndoButton(opInfo.ui?.undo);
            return;
        }
        this.completeOpInfo(opInfo);
        this.opInfo = opInfo;
        if (opInfo.prompt) {
            this.bga.statusBar.setTitle(this.getTr(opInfo.prompt, opInfo));
        }
        if (opInfo.subtitle)
            this.setSubPrompt(this.getTr(opInfo.subtitle, opInfo), opInfo);
        else
            this.setSubPrompt(this.getReasonText(opInfo.data.reason));
        if (opInfo.err) {
            const button = this.bga.statusBar.addActionButton(this.getTr(opInfo.err, opInfo), () => { }, {
                color: "alert",
                id: "button_err"
            });
        }
        const multiselect = this.isMultiSelectArgs(opInfo);
        const sortedTargets = Object.keys(opInfo.info);
        sortedTargets.sort((a, b) => opInfo.info[a].o - opInfo.info[b].o);
        for (const target of sortedTargets) {
            const paramInfo = opInfo.info[target];
            if (paramInfo.sec) {
                continue; // secondary buttons
            }
            const div = $(target);
            const q = paramInfo.q;
            const active = q == 0;
            // simple case we select element (dom node) which is target of operation
            if (div && active && paramInfo.noactive !== true) {
                const doNotShowActive = paramInfo.noactive ?? opInfo.ui.noactive ?? false;
                if (doNotShowActive == false) {
                    div.classList.add(this.classActiveSlot);
                    div.dataset.targetOpType = opInfo.type;
                }
            }
            // we also can have one addition way of selection (possibly)
            let altNode;
            if (opInfo.ui.replicate == true) {
                altNode = this.replicateTargetOnSelectionArea(target, paramInfo);
            }
            if (opInfo.ui.imagebuttons == true) {
                altNode = this.replicateTargetOnToolbar(target, paramInfo);
            }
            if (!altNode && (opInfo.ui.buttons || !div)) {
                altNode = this.createTargetButton(target, paramInfo);
            }
            if (!altNode)
                continue;
            altNode.dataset.targetId = target;
            altNode.dataset.targetOpType = opInfo.type;
            if (!active) {
                altNode.title = this.getTr(paramInfo.err ?? _("Operation cannot be performed now"), paramInfo);
                altNode.classList.add(this.classButtonDisabled);
            }
            else {
                const title = paramInfo.tooltip;
                if (title)
                    altNode.title = this.getTr(title, paramInfo);
                else
                    this.updateTooltip(target, altNode);
            }
            if (paramInfo.max !== undefined) {
                altNode.dataset.max = String(paramInfo.max);
            }
            else {
                altNode.dataset.max = "1";
            }
        }
        if (opInfo.ui.buttons == false || opInfo.ui.replicate) {
            this.addShowMeButton(true);
        }
        // secondary buttons
        for (const target of sortedTargets) {
            const paramInfo = opInfo.info[target];
            if (paramInfo.sec) {
                // skip, whatever TODO: anytime
                const color = paramInfo.color ?? "secondary";
                const call = paramInfo.call ?? target;
                const button = this.bga.statusBar.addActionButton(this.getTargetButtonName(target, paramInfo), () => this.bga.actions.performAction(`action_${call}`, {
                    data: JSON.stringify({ target })
                }), {
                    color: color,
                    id: "button_" + target,
                    confirm: this.getTr(paramInfo.confirm)
                });
                button.dataset.targetId = target;
            }
        }
        if (multiselect) {
            this.activateMultiSelectPrompt(opInfo);
        }
        // need a global condition when this can be added
        this.addUndoButton(this.bga.players.isCurrentPlayerActive() || opInfo.ui.undo);
    }
    createTargetButton(target, paramInfo) {
        const q = paramInfo.q;
        const active = q == 0;
        const color = paramInfo.color ?? this.opInfo.ui.color;
        const button = this.bga.statusBar.addActionButton(this.getTargetButtonName(target, paramInfo), (event) => this.onToken(event), {
            color: color,
            disabled: !active,
            id: "button_" + target
        });
        return button;
    }
    replicateTargetOnToolbar(target, paramInfo) {
        const q = paramInfo.q;
        const active = q == 0;
        const color = paramInfo.color ?? "secondary";
        const div = $(target);
        let cloneHtml = this.createCustomButtonImageHtml(target, paramInfo);
        if (!cloneHtml && div) {
            const clone = div.cloneNode(true);
            clone.id = target + "_temp";
            clone.classList.remove(this.classActiveSlot);
            clone.classList.add(this.classActiveSlotHidden);
            cloneHtml = clone.outerHTML;
        }
        if (!cloneHtml) {
            return undefined;
        }
        const button = this.bga.statusBar.addActionButton(cloneHtml, (event) => this.onToken(event), {
            color,
            disabled: !active,
            id: "button_" + target
        });
        return button;
    }
    createCustomButtonImageHtml(target, paramInfo) {
        return undefined;
    }
    replicateTargetOnSelectionArea(target, paramInfo) {
        const div = $(target);
        if (!div)
            return undefined;
        const parent = document.createElement("div");
        parent.classList.add("target_container");
        const clone = div.cloneNode(true);
        clone.id = div.id + "_temp";
        parent.appendChild(clone);
        $("selection_area").appendChild(parent);
        clone.addEventListener("click", (event) => this.onToken(event));
        clone.classList.remove(this.classActiveSlot);
        clone.classList.add(this.classActiveSlotHidden);
        return clone;
    }
    getReasonText(reason) {
        if (!reason)
            return "";
        return _("Reason:") + " " + this.getTokenName(reason);
    }
    getTargetButtonName(target, paramInfo) {
        const div = $(target);
        let name = paramInfo.name;
        if (!name && div) {
            name = div.dataset.name;
        }
        if (!name)
            return this.getTokenName(target);
        else
            return this.getTr(name, paramInfo.args ?? paramInfo);
    }
    isMultiSelectArgs(args) {
        return args.ttype == "token_count" || args.ttype == "token_array";
    }
    isMultiCountArgs(args) {
        return args.ttype == "token_count";
    }
    onLeavingState(stateName, args) {
        super.onLeavingState(stateName, args);
        $("button_undo")?.remove();
    }
    /** default click processor */
    onToken(event, fromMethod) {
        console.log(event);
        let id = this.onClickSanity(event);
        if (!id) {
            return true;
        }
        if (!fromMethod)
            fromMethod = "onToken";
        event.stopPropagation();
        event.preventDefault();
        const ttype = this.opInfo?.ttype;
        if (ttype) {
            var methodName = "onToken_" + ttype;
            let ret = this.callfn(methodName, id, event.currentTarget);
            if (ret === undefined)
                return false;
            return true;
        }
        else if (!this.isActiveSlot(id)) {
            return this.onTokenNonActive(event);
        }
        console.error("no handler for ", ttype);
        return false;
    }
    onTokenNonActive(event, fromMethod) {
        event.stopPropagation();
        event.preventDefault();
        return false;
    }
    onToken_token(target) {
        if (!target)
            return false;
        this.resolveAction({ target });
        return true;
    }
    onToken_token_array(target, node) {
        return this.onMultiCount(target, this.opInfo, node);
    }
    onToken_token_count(target, node) {
        return this.onMultiCount(target, this.opInfo, node);
    }
    activateMultiSelectPrompt(opInfo) {
        const ttype = opInfo.ttype;
        const buttonName = _("Submit");
        const doneButtonId = "button_done";
        const resetButtonId = "button_reset";
        this.bga.statusBar.addActionButton(buttonName, () => {
            const res = {};
            const count = this.getMultiSelectCountAndSync(res);
            if (opInfo.ttype == "token_count") {
                this.resolveAction({ target: res, count });
            }
            else {
                this.resolveAction({ target: Object.keys(res), count });
            }
        }, {
            color: "primary",
            id: doneButtonId
        });
        this.bga.statusBar.addActionButton(_("Reset"), () => {
            const allSel = document.querySelectorAll(`.${this.classSelectedAlt},.${this.classSelected}`);
            allSel.forEach((node) => {
                delete node.dataset.count;
            });
            this.removeAllClasses(this.classSelected, this.classSelectedAlt);
            this.onMultiSelectionUpdate(opInfo);
        }, {
            color: "alert",
            id: resetButtonId
        });
        // this.replicateTokensOnToolbar(opInfo, (target) => {
        //   return this.onMultiCount(target, opInfo);
        // });
        this.onMultiSelectionUpdate(opInfo);
        // this[`onToken_${ttype}`] = (tid: string, o: OpInfo, node: HTMLElement) => {
        //   return this.onMultiCount(tid, opInfo, node);
        // };
    }
    onUpdateActionButtons_PlayerTurnConfirm(args) {
        this.bga.statusBar.addActionButton(_("Confirm"), () => this.resolveAction());
        this.addUndoButton();
    }
    resolveAction(args = {}) {
        this.bga.actions
            .performAction("action_resolve", {
            data: JSON.stringify(args)
        })
            .then((x) => {
            console.log("action complete", x);
        })
            .catch((e) => {
            this.setSubPrompt(e.message, e.args);
        });
    }
    addUndoButton(cond = true) {
        if (!$("button_undo") && !this.bga.players.isCurrentPlayerSpectator() && cond) {
            const div = this.bga.statusBar.addActionButton(_("Undo"), () => this.bga.actions
                .performAction("action_undo", [], {
                checkAction: false
            })
                .catch((e) => {
                this.setSubPrompt(e.message, e.args);
            }), {
                color: "alert",
                id: "button_undo"
            });
            div.classList.add("button_undo");
            div.title = _("Undo all possible steps");
            $("undoredo_wrap")?.appendChild(div);
            // const div2 = this.addActionButtonColor("button_undo_last", _("Undo"), () => this.sendActionUndo(-1), "red");
            // div2.classList.add("button_undo");
            // div2.title = _("Undo One Step");
            // $("undoredo_wrap")?.appendChild(div2);
        }
    }
    getMultiSelectCountAndSync(result = {}) {
        // sync alternative selection on toolbar
        const allSel = document.querySelectorAll(`.${this.classSelected}`);
        const selectedAlt = this.classSelectedAlt;
        this.removeAllClasses(selectedAlt);
        let totalCount = 0;
        allSel.forEach((node) => {
            let altnode = document.querySelector(`[data-target-id="${node.id}"]`);
            // if (!altnode) {
            //   altnode = $(node.dataset.targetId);
            // }
            if (altnode && altnode != node) {
                altnode.classList.add(selectedAlt);
            }
            const cnode = altnode ?? node;
            const tid = cnode.dataset.targetId ?? node.id;
            const count = cnode.dataset.count === undefined ? 1 : Number(cnode.dataset.count);
            result[tid] = count;
            totalCount += count;
        });
        return totalCount;
    }
    onMultiCount(tid, opInfo, clicknode) {
        if (!tid)
            return false;
        let node = clicknode ?? $(tid);
        let altnode;
        if (clicknode) {
            altnode = $(clicknode.dataset.primaryId);
        }
        if (!altnode)
            altnode = document.querySelector(`[data-target-id="${tid}"]`);
        const cnode = altnode ?? node;
        const count = Number(cnode.dataset.count ?? 0);
        cnode.dataset.count = String(count + 1);
        const max = Number(cnode.dataset.max ?? 1);
        const selNode = cnode;
        if (count + 1 > max) {
            cnode.dataset.count = "0";
            selNode.classList.remove(this.classSelected);
        }
        else {
            selNode.classList.add(this.classSelected);
        }
        this.onMultiSelectionUpdate(opInfo);
        return true;
    }
    onMultiSelectionUpdate(opInfo) {
        const ttype = opInfo.ttype;
        const skippable = false; // XXX
        const doneButtonId = "button_done";
        const resetButtonId = "button_reset";
        const skipButton = $("button_skip");
        const buttonName = _("Submit");
        // sync real selection to alt selection on toolbar
        const count = this.getMultiSelectCountAndSync();
        const doneButton = $(doneButtonId);
        if (doneButton) {
            if ((count == 0 && skippable) || count < opInfo.mcount) {
                doneButton.classList.add(this.classButtonDisabled);
                doneButton.title = _("Cannot use this action because insuffient amount of elements selected");
            }
            else if (count > opInfo.count) {
                doneButton.classList.add(this.classButtonDisabled);
                doneButton.title = _("Cannot use this action because superfluous amount of elements selected");
            }
            else {
                doneButton.classList.remove(this.classButtonDisabled);
                doneButton.title = "";
            }
            $(doneButtonId).innerHTML = buttonName + ": " + count;
        }
        if (count > 0) {
            $(resetButtonId)?.classList.remove(this.classButtonDisabled);
            if (skipButton) {
                skipButton.classList.add(this.classButtonDisabled);
                skipButton.title = _("Cannot use this action because there are some elements selected");
            }
        }
        else {
            $(resetButtonId)?.classList.add(this.classButtonDisabled);
            if (skipButton) {
                skipButton.title = "";
                skipButton.classList.remove(this.classButtonDisabled);
            }
        }
    }
    setSubPrompt(text, args = {}) {
        if (!text)
            text = "";
        if (!args)
            args = [];
        const message = this.format_string_recursive(this.getTr(text, args), args);
        // have to set after otherwise status update wipes it
        setTimeout(() => {
            $("gameaction_status").innerHTML = `<div class="subtitle">${message}</div>`;
        }, 100);
    }
    completeOpInfo(opInfo) {
        var _a, _b;
        try {
            // server may skip sending some data, this will feel all omitted fields
            if (opInfo.data?.count !== undefined && opInfo.count === undefined)
                opInfo.count = parseInt(opInfo.data.count);
            if (opInfo.data?.mcount !== undefined && opInfo.mcount === undefined)
                opInfo.mcount = parseInt(opInfo.data.mcount);
            if (opInfo.void === undefined)
                opInfo.void = false;
            opInfo.confirm = opInfo.confirm ?? false;
            if (!opInfo.info)
                opInfo.info = {};
            if (!opInfo.target)
                opInfo.target = [];
            if (!opInfo.ui)
                opInfo.ui = {};
            const infokeys = Object.keys(opInfo.info);
            if (infokeys.length == 0 && opInfo.target.length > 0) {
                opInfo.target.forEach((element) => {
                    opInfo.info[element] = { q: 0 };
                });
            }
            else if (infokeys.length > 0 && opInfo.target.length == 0) {
                infokeys.forEach((element) => {
                    if (opInfo.info[element].q == 0)
                        opInfo.target.push(element);
                });
            }
            // set default order
            let i = 1;
            for (const target of opInfo.target) {
                const paramInfo = opInfo.info[target];
                if (!paramInfo.o)
                    paramInfo.o = i;
                i++;
            }
            if (opInfo.info.confirm && !opInfo.info.confirm.name) {
                opInfo.info.confirm.name = _("Confirm");
            }
            if (opInfo.info.skip && !opInfo.info.skip.name) {
                opInfo.info.skip.name = _("Skip");
            }
            if (this.isMultiSelectArgs(opInfo)) {
                opInfo.ui.replicate = true;
                (_a = opInfo.ui).color ?? (_a.color = "secondary");
            }
            else {
                (_b = opInfo.ui).color ?? (_b.color = "primary");
            }
            if (opInfo.ui.buttons === undefined && !opInfo.ui.replicate) {
                opInfo.ui.buttons = true;
            }
        }
        catch (e) {
            console.error(e);
        }
    }
}

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */
class PlayerTurn {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }
    onEnteringState(args, isCurrentPlayerActive) {
        if (args._private)
            this.game.onEnteringState_PlayerTurn(args._private);
        else
            this.game.onEnteringState_PlayerTurn(args);
    }
    onLeavingState(args, isCurrentPlayerActive) {
        this.game.onLeavingState("PlayerTurn", args);
    }
    onPlayerActivationChange(args, isCurrentPlayerActive) { }
}
class Game extends GameMachine {
    constructor(bga) {
        super(bga);
        console.log("fate constructor");
        this.playerTurn = new PlayerTurn(this, bga);
        this.bga.states.register("PlayerTurn", this.playerTurn);
    }
    setup(gamedatas) {
        console.log("Starting game setup");
        super.setup(gamedatas);
        placeHtml(`<div id="thething"></div>`, this.bga.gameArea.getElement());
        placeHtml(`<div id="limbo"></div>`, this.bga.gameArea.getElement());
        placeHtml(`<div id="supply" class="supply"></div>`, "thething");
        placeHtml(`<div id="supply_monster" class="supply"></div>`, "supply");
        placeHtml(`<div id="supply_crystal_green" class="supply"></div>`, "supply");
        placeHtml(`<div id="supply_crystal_red" class="supply"></div>`, "supply");
        placeHtml(`<div id="supply_crystal_yellow" class="supply"></div>`, "supply");
        placeHtml(`<div id="supply_dice" class="supply"></div>`, "supply");
        placeHtml(`<div id="players_panels"></div>`, "thething");
        const mapWrapper = "map_wrapper";
        placeHtml(`<div id="${mapWrapper}" class="${mapWrapper}"></div>`, "thething");
        this.createMap($(mapWrapper));
        placeHtml(`<div id="timetrack_1"></div>`, mapWrapper);
        placeHtml(`<div id="timetrack_2"></div>`, mapWrapper);
        placeHtml(`<div id="display_monsterturn"></div>`, $("thething"));
        placeHtml(`<div id="deck_monster_yellow" class="deck deck_monster"></div>`, "display_monsterturn");
        placeHtml(`<div id="deck_monster_red" class="deck deck_monster"></div>`, "display_monsterturn");
        Object.values(gamedatas.players).forEach((player) => {
            // template leftovers TODO: remove
            //const playerId = Number(player.id);
            // this.bga.playerPanels.getElement(playerId).insertAdjacentHTML(
            //   "beforeend",
            //   `
            //           <span id="energy-player-counter-${playerId}"></span> Energy
            //       `
            // );
            // const counter = new ebg.counter();
            // counter.create(`energy-player-counter-${playerId}`, {
            //   value: (player as any).energy,
            //   playerCounter: "energy",
            //   playerId
            // });
            placeHtml(`<div id="tableau_${player.color}">
                    <strong>${player.name}</strong>
                    <div>Player zone content goes here</div>
                </div>`, "players_panels");
        });
        this.setupGame(gamedatas);
        this.setupNotifications();
        console.log("Ending game setup");
    }
    createMap(parent) {
        // create map area: Pointy-top hex grid, hexagonal shape with side length 9.
        // Shifted axial coordinates: center at (9,9), range 1..17. Hex boundary: |q-9| + |r-9| + |q+r-18| <= 16
        // Horizontal rows by r: row pattern 9, 10, 11, ..., 17, ..., 11, 10, 9
        const GRID_N = 8; // hex radius
        const GRID_C = GRID_N + 1; // center offset (9)
        const COLS = 2 * GRID_N + 1; // 17
        const ROWS = 3 * GRID_N + 2; // 26
        const hexes = [];
        for (let r = 1; r <= COLS; r++) {
            const r0 = r - GRID_C; // zero-based r
            const qMin = Math.max(1, 1 - r0);
            const qMax = Math.min(COLS, COLS - r0);
            for (let q = qMin; q <= qMax; q++) {
                const q0 = q - GRID_C; // zero-based q
                // Position as % of map_area: px/mapW*100 and py/mapH*100
                const leftPct = ((GRID_N + q0 + r0 / 2) / COLS) * 100;
                const topPct = ((1.5 * (GRID_N + r0)) / ROWS) * 100;
                const hexId = `hex_${q}_${r}`;
                const terrain = this.getRulesFor(hexId, "terrain", "");
                const loc = this.getRulesFor(hexId, "loc", "");
                hexes.push(`<div class="hex terrain_${terrain}" id="${hexId}" style="left:${leftPct}%;top:${topPct}%;" data-q="${q}" data-r="${r}" data-loc="${loc}"></div>`);
            }
        }
        const hexHtml = hexes.join("\n");
        placeHtml(`<div id="map_area">${hexHtml}</div>`, parent);
        parent.querySelectorAll(".hex").forEach((node) => {
            this.addListenerWithGuard(node, (e) => this.onToken(e));
            this.updateTooltip(node.id);
        });
    }
    getPlaceRedirect(tokenInfo, args = {}) {
        const result = tokenInfo;
        const loc = tokenInfo.location;
        // Stack monsters by type in supply: create sub-container per monster type
        if (loc === "supply_monster") {
            const monsterType = getPart(tokenInfo.key, 0) + "_" + getPart(tokenInfo.key, 1); // e.g. "monster_goblin"
            const subId = "supply_" + monsterType;
            if (!$(subId)) {
                placeHtml(`<div id="${subId}" class="pile_monster ${monsterType}"></div>`, "supply_monster");
            }
            result.location = subId;
        }
        return result;
    }
    updateTokenDisplayInfo(tokenInfo) {
        // override to generate dynamic tooltips and such
        const mainType = tokenInfo.mainType;
        const token = $(tokenInfo.tokenId);
        const parentId = token?.parentElement?.id;
        const state = parseInt(token?.dataset.state);
        const tokenId = tokenInfo.tokenId;
        const subType = getPart(tokenId, 1);
        switch (mainType) {
            case "card": {
                if (subType === "monster") {
                    const spawnLoc = this.getTokenName(tokenInfo.spawnloc);
                    tokenInfo.tooltip = this.ttSection(_("Spawn Location"), spawnLoc);
                    if (tokenInfo.ftext)
                        tokenInfo.tooltip += this.ttSection(_("Flavor"), String(tokenInfo.ftext));
                }
                break;
            }
            case "monster": {
                tokenInfo.tooltip = this.ttSection(_("Faction"), this.getTokenName(tokenInfo.faction));
                tokenInfo.tooltip += this.ttSection(_("Rank"), String(tokenInfo.rank));
                tokenInfo.tooltip += this.ttSection(_("Strength"), String(tokenInfo.strength));
                tokenInfo.tooltip += this.ttSection(_("Health"), String(tokenInfo.health));
                if (tokenInfo.move)
                    tokenInfo.tooltip += this.ttSection(_("Move"), String(tokenInfo.move));
                if (tokenInfo.armor)
                    tokenInfo.tooltip += this.ttSection(_("Armor"), String(tokenInfo.armor));
                if (tokenInfo.xp)
                    tokenInfo.tooltip += this.ttSection(_("XP"), String(tokenInfo.xp));
                break;
            }
            case "house": {
                tokenInfo.tooltip = this.ttSection(_("Type"), String(tokenInfo.name));
                break;
            }
            case "hex": {
                const q = getPart(tokenId, 1);
                const r = getPart(tokenId, 2);
                if (!r)
                    return;
                //             "hex_9_1" => [
                //         "location" => "map_hexes",
                //         "x" => 9,
                //         "y" => 1,
                //         "terrain" => "forest",
                //         "loc" => "DarkForest",
                //         "c" => "red",
                // ],
                const locname = this.getTokenName(tokenInfo.loc);
                const areacolor = this.getTokenName(tokenInfo.c);
                const terrainname = this.getTokenName(tokenInfo.terrain);
                tokenInfo.name = _("Hex") + ` (${r},${q})`;
                tokenInfo.tooltip = this.ttSection(_("Terrain"), terrainname);
                tokenInfo.tooltip += this.ttSection(_("Coords"), `(${r},${q})`);
                if (tokenInfo.loc)
                    tokenInfo.tooltip += this.ttSection(_("Location"), locname);
                if (tokenInfo.c)
                    tokenInfo.tooltip += this.ttSection(_("Location Color"), areacolor);
            }
        }
    }
    setupNotifications() {
        console.log("notifications subscriptions setup");
        // automatically listen to the notifications, based on the `notif_xxx` function on this class.
        this.bga.notifications.setupPromiseNotifications({
            minDuration: 1,
            minDurationNoText: 1,
            logger: console.log, // show notif debug informations on console. Could be console.warn or any custom debug function (default null = no logs)
            //handlers: [this, this.tokens],
            onStart: (notifName, msg, args) => {
                if (msg)
                    this.setSubPrompt(msg, args);
            }
            // onEnd: (notifName, msg, args) => this.setSubPrompt("", args)
        });
    }
    async notif_tokenMoved(args) {
        return super.notif_tokenMoved(args);
    }
    async notif_counter(args) {
        return super.notif_counter(args);
    }
    async notif_message(args) {
        //console.log("notif", args);
        return gameui.wait(1);
    }
    async notif_undoMove(args) {
        console.log("notif", args);
        return gameui.wait(1);
    }
    async notif_lastTurn(args) {
        //this.gamedatas.lastTurn = true;
        //this.updateBanner();
    }
}

export { Game };
