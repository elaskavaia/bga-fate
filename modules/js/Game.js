/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */
class Game0Basics {
    constructor(bga) {
        this.bga = bga;
        this.defaultTooltipDelay = 800;
        this.inSetup = true;
        this.lastMoveId = 0;
        this.prevLogId = 0;
        //console.log("game constructor");
    }
    // proxies for GameGui properties/methods accessed via gameui
    get player_id() {
        return gameui.player_id;
    }
    format_string_recursive(log, args) {
        const gameacc = gameui;
        const save = gameacc.prevent_error_rentry;
        try {
            // hack to hide errors
            gameacc.prevent_error_rentry = 11;
            return gameui.format_string_recursive(log, args);
        }
        catch (e) {
            console.error(e);
            return log;
        }
        finally {
            gameacc.prevent_error_rentry = save;
        }
    }
    addTooltipHtml(nodeId, html, delay) {
        gameui.addTooltipHtml(nodeId, html, delay);
    }
    bgaAnimationsActive() {
        return gameui.bgaAnimationsActive();
    }
    gameAnimationsActive() {
        return this.bgaAnimationsActive() && !this.inSetup;
    }
    setup(gamedatas) {
        this.inSetup = true;
        this.gamedatas = gamedatas;
        console.log("Starting game setup", gamedatas);
        const first_player_id = Object.keys(gamedatas.players)[0];
        if (!this.bga.players.isCurrentPlayerSpectator())
            this.player_color = gamedatas.players[this.player_id].color;
        else
            this.player_color = gamedatas.players[first_player_id].color;
        // caller must set inSetup to false
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
        const log = this.format_string_recursive(gameui.clienttranslate_string(name), args);
        return log;
    }
    getFormatted(name) {
        if (!name)
            return "";
        if (name.log !== undefined) {
            const notif = name;
            const log = this.format_string_recursive(notif.log, notif.args);
            return log;
        }
        const log = this.format_string_recursive(name, {});
        return log;
    }
    setActionStatus(text, args = {}) {
        if (!text)
            text = "";
        const message = this.getTr(text, args);
        const node = document.querySelector("#gameaction_status");
        if (node)
            node.innerHTML = message;
        this.bga.statusBar.setTitle(message);
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
    // Player ids in turn order, rotated so current player is first (unless spectator)
    getOrderedPlayerIds(gamedatas) {
        const ids = gamedatas.playerorder.map(Number);
        if (this.bga.players.isCurrentPlayerSpectator())
            return ids;
        const idx = ids.indexOf(this.player_id);
        if (idx <= 0)
            return ids;
        return ids.slice(idx).concat(ids.slice(0, idx));
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
    notif_log(args, notif) {
        // if (notif.log) {
        //   console.log(notif.log, notif.args);
        //   var message = this.format_string_recursive(notif.log, notif.args);
        //   if (message != notif.log) console.log(message);
        // } else {
        if (notif.log) {
            var message = this.format_string_recursive(notif.log, notif.args);
            delete notif.log;
            console.log("debug log", message, args);
        }
        else {
            console.log("hidden log", args);
        }
    }
    notif_message_warning(notif) {
        if (this.gameAnimationsActive()) {
            var message = this.format_string_recursive(notif.log, notif.args);
            this.bga.dialogs.showMessage(_("Warning:") + " " + message, "info");
        }
    }
    notif_message_info(notif) {
        if (this.gameAnimationsActive()) {
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

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */
class LaAnimations {
    constructor(bga) {
        this.bga = bga;
        this.defaultAnimationDuration = 500;
    }
    setup() {
        placeHtml(`<div id="oversurface"></div>`, this.bga.gameArea.getElement());
    }
    gameAnimationsActive() {
        return this.bga.gameui.bgaAnimationsActive();
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
        let clone;
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
        // Fall back to parent's rect when source is display:none (e.g. cards stacked in a deck) — otherwise animation flies from (0,0)
        let elemRect = elem.getBoundingClientRect();
        if (elemRect.width === 0 && elemRect.height === 0 && elem.parentElement) {
            elemRect = elem.parentElement.getBoundingClientRect();
        }
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
    /**
     * Pulse an element: scale up then back to normal size.
     * If called again while already pulsing, queues the next pulse after the current one.
     */
    pulse(targetId, scale = 2, duration = 400) {
        if (!this.gameAnimationsActive())
            return;
        const node = $(targetId);
        if (!node)
            return;
        const pending = Number(node.dataset.pulseQueue || 0);
        if (pending > 0) {
            node.dataset.pulseQueue = String(pending + 1);
            return;
        }
        node.dataset.pulseQueue = "1";
        this.doPulse(node, scale, duration);
    }
    doPulse(node, scale, duration) {
        const half = duration / 2;
        node.style.transitionDuration = half + "ms";
        node.style.transitionProperty = "transform";
        node.style.transitionTimingFunction = "ease-out";
        node.offsetHeight;
        node.style.transform = `scale(${scale})`;
        setTimeout(() => {
            node.style.transitionTimingFunction = "ease-in";
            node.style.transform = "";
            setTimeout(() => {
                const remaining = Number(node.dataset.pulseQueue || 0) - 1;
                if (remaining > 0) {
                    node.dataset.pulseQueue = String(remaining);
                    this.doPulse(node, scale, duration);
                }
                else {
                    delete node.dataset.pulseQueue;
                    node.style.removeProperty("transition-duration");
                    node.style.removeProperty("transition-property");
                    node.style.removeProperty("transition-timing-function");
                }
            }, half);
        }, half);
    }
    /**
     * Clone an element, position it over a target, then float up and fade out.
     * The original element is not affected.
     */
    evaporate(mobileId, targetId, duration) {
        const mobileNode = $(mobileId);
        const targetNode = $(targetId);
        if (!mobileNode || !targetNode)
            return;
        if (duration === undefined)
            duration = 1200;
        // Project a clone of the target to get its position on oversurface
        const targetClone = this.projectOnto(targetNode, "_evap_dest");
        const targetLeft = targetClone.style.left;
        const targetTop = targetClone.style.top;
        targetClone.remove();
        // Project a clone of the mobile onto oversurface
        const clone = this.projectOnto(mobileNode, "_evap");
        // Reposition clone over the target (centered horizontally, above vertically)
        clone.style.left = targetLeft;
        clone.style.top = targetTop;
        clone.style.pointerEvents = "none";
        clone.offsetHeight; // force reflow
        // Animate: float up + fade out
        clone.style.transitionDuration = duration + "ms";
        clone.style.transitionProperty = "opacity, transform";
        clone.style.transitionTimingFunction = "ease-out";
        clone.offsetHeight; // force reflow
        clone.style.opacity = "0";
        clone.style.transform = (clone.style.transform || "") + " translateY(-60px) scale(1.3)";
        setTimeout(() => clone.remove(), duration);
    }
    /**
     * Shrink and fade an element in place.
     * The element is hidden (opacity 0) during the animation; a clone performs the visual effect.
     */
    shrinkAndFade(mobileId, duration) {
        const mobileNode = $(mobileId);
        if (!mobileNode)
            return Promise.resolve();
        if (duration === undefined)
            duration = 600;
        const clone = this.projectOnto(mobileNode, "_shrink");
        clone.style.pointerEvents = "none";
        mobileNode.style.opacity = "0";
        clone.offsetHeight; // force reflow
        clone.style.transitionDuration = duration + "ms";
        clone.style.transitionProperty = "opacity, transform";
        clone.style.transitionTimingFunction = "ease-in";
        clone.offsetHeight; // force reflow
        clone.style.opacity = "0";
        clone.style.transform = (clone.style.transform || "") + " scale(0)";
        return new Promise((resolve) => {
            setTimeout(() => {
                clone.remove();
                mobileNode.style.removeProperty("opacity");
                resolve();
            }, duration);
        });
    }
    cardFlip(mobileId, newState, duration, onEnd, frontNode) {
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
        // Front face. Single-node: project mobileNode at its pre-state appearance.
        // Two-node: anchor to mobileNode's rect (frontNode may be in limbo with no rect), swap sprite classes to frontNode's.
        let clone;
        if (frontNode) {
            clone = this.projectOnto(mobileNode, "_temp");
            clone.innerHTML = "";
            const frontEl = $(frontNode);
            const phantomClasses = Array.from(clone.classList).filter((c) => c.startsWith("phantom"));
            clone.className = "";
            clone.classList.add(...Array.from(frontEl.classList));
            clone.classList.add(...phantomClasses);
        }
        else {
            clone = this.projectOnto(mobileNode, "_temp");
            clone.innerHTML = "";
            mobileNode.dataset.state = newState;
            mobileNode.offsetHeight; // recalc
        }
        const desti = this.projectOnto(mobileNode, "_temp2"); // invisible destination on top of new parent
        desti.innerHTML = "";
        mobileNode.style.opacity = "0"; // hide original
        mobileNode.style.pointerEvents = "none"; // opacity:0 still hit-tests — block tooltips/clicks during the flip
        // Two-layer wrapper: outer keeps the position-correcting matrix transform; inner gets the flip animation.
        // (If we put both on one element, the @keyframes transform would wipe the position matrix during the animation.)
        placeHtml(`<div id="card_temp"><div id="card_temp_inner"></div></div>`, "oversurface");
        const group = $("card_temp");
        const inner = $("card_temp_inner");
        group.style.left = desti.style.left;
        group.style.top = desti.style.top;
        group.style.transform = desti.style.transform;
        group.style.width = desti.style.width;
        group.style.height = desti.style.height;
        group.style.position = "absolute";
        group.style.perspective = "40em";
        inner.style.position = "absolute";
        inner.style.width = "100%";
        inner.style.height = "100%";
        inner.style.transformStyle = "preserve-3d";
        inner.appendChild(clone);
        inner.appendChild(desti);
        clone.style.removeProperty("left");
        clone.style.removeProperty("top");
        desti.style.removeProperty("left");
        desti.style.removeProperty("top");
        clone.style.width = "100%";
        clone.style.height = "100%";
        desti.style.width = "100%";
        desti.style.height = "100%";
        desti.style.transform = "rotateY(180deg)";
        desti.style.backfaceVisibility = "hidden";
        clone.style.backfaceVisibility = "hidden";
        // .phantom may carry its own animation/transform — suppress on these clones so only the wrapper's flip runs
        clone.style.animation = "none";
        desti.style.animation = "none";
        try {
            inner.style.animation = `flip ${duration}ms`;
            setTimeout(() => {
                mobileNode.style.removeProperty("opacity"); // restore visibility of original
                mobileNode.style.removeProperty("pointer-events");
                group.remove();
                if (onEnd)
                    onEnd(mobileNode);
            }, duration);
        }
        catch (e) {
            // if bad thing happen we have to clean up clones
            console.error("ERR:C01:animation error", e);
            mobileNode.style.removeProperty("pointer-events");
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

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */
const BgaAnimations = (await globalThis.importEsmLib("bga-animations", "1.x"));

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */
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
    setupTokens(gamedatas) {
        this.tokenInfoCache = {};
        // create the animation manager, and bind it to the `game.bgaAnimationsActive()` function
        this.animationManager = new BgaAnimations.Manager({
            animationsActive: () => this.bgaAnimationsActive()
        });
        this.animationLa = new LaAnimations(this.bga);
        this.animationLa.setup();
        this.animationLa.gameAnimationsActive = () => this.gameAnimationsActive();
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
        this.placeAllTokens();
        this.updateCountersSafe(this.gamedatas.counters);
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
                    let deckId = key.replace("counter_", "");
                    if (!$(deckId)) {
                        deckId = "limbo";
                    }
                    placeHtml(`<div id='${key}' class='counter'></div>`, deckId);
                    node = $(key);
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
    placeAllTokens() {
        console.log("Place tokens");
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
        if (displayInfo.tc)
            tokenNode.dataset.tc = displayInfo.tc;
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
     * Click handler helper: resolves the clicked element's id (walking up to an active parent if inside "thething"),
     * checks for help-mode intercept, and returns whether the click is blocked (no active_slot) or actionable.
     */
    onClickSanity(event) {
        let id = event.currentTarget.id;
        let target = event.target;
        if (id == "thething") {
            let node = this.findActiveParent(target);
            id = node?.id;
            target = node;
        }
        const result = {
            targetId: id,
            targetNode: target,
            blocked: true,
            active: false
        };
        console.log("on slot " + id);
        if (!id)
            return result;
        if (this.showHelp(id))
            return result;
        if (!this.checkActiveSlot(id, false)) {
            result.blocked = false;
            return result;
        }
        if (target?.dataset.targetId)
            result.targetId = target.dataset.targetId;
        result.active = true;
        return result;
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
        if (!node)
            return false;
        if (node.classList.contains(this.classActiveSlot)) {
            return true;
        }
        if (node.classList.contains(this.classActiveSlotHidden)) {
            return true;
        }
        if (node.id.startsWith("button_")) {
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
        this.updateTooltip(tokenId, undefined, { force: true });
        this.updateTooltip(tokenInfo.location, undefined, { force: true });
    }
    prepareToken(tokenId, tokenDbInfo, args = {}) {
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
        const placeInfo = this.prepareToken(tokenId, tokenDbInfo);
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
            const placeInfo = this.prepareToken(tokenId, tokenDbInfo, args);
            if (!placeInfo) {
                return;
            }
            const tokenNode = $(tokenId);
            let animTime = placeInfo.duration ?? this.defaultAnimationDuration;
            if (this.game.bgaAnimationsActive() == false || args.noa || placeInfo.noa || placeInfo.duration === 0 || !tokenNode.parentNode) {
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
    updateTooltip(tokenId, attachTo, options = {}) {
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
        var tokenInfo = this.getTokenDisplayInfo(tokenId, options.force);
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
        var main = this.getTooltipHtmlForTokenInfo(tokenInfo, attachNode);
        if (main) {
            attachNode.classList.add("withtooltip");
            if (attachNode.id != tokenId)
                attachNode.dataset.tt = tokenId; // id of token that provides the tooltip
            //console.log("addTooltipHtml", attachNode.id);
            this.game.addTooltipHtml(attachNode.id, main, options.delay ?? this.game.defaultTooltipDelay);
            attachNode.removeAttribute("title"); // unset title so both title and tooltip do not show up
            this.handleStackedTooltips(attachNode);
        }
        else {
            attachNode.classList.remove("withtooltip");
        }
    }
    handleStackedTooltips(attachNode) { }
    getTooltipHtmlForToken(token) {
        const tokenInfo = this.getTokenDisplayInfo(token, true);
        return this.getTooltipHtmlForTokenInfo(tokenInfo, $(token));
    }
    getTooltipHtmlForTokenInfo(tokenInfo, attachNode) {
        if (!tokenInfo)
            return "";
        const dt = this.getDynamicTooltip(tokenInfo, attachNode);
        return this.getTooltipHtml(tokenInfo.name, tokenInfo.tooltip, dt, tokenInfo.imageTypes, tokenInfo.imageData);
    }
    getDynamicTooltip(tokenInfo, attachNode) {
        return undefined;
    }
    getTokenName(tokenId, force = true) {
        var tokenInfo = this.getTokenDisplayInfo(tokenId);
        if (tokenInfo) {
            return this.game.getTr(tokenInfo.name);
        }
        else {
            if (!force)
                return "";
            return "? " + tokenId;
        }
    }
    getTooltipHtml(name, message, dynamic, imgTypes = "", imageData) {
        if (name == null || message == "-")
            return "";
        if (!message)
            message = "";
        var divImg = "";
        var containerType = "tooltipcontainer ";
        if (imgTypes && !imgTypes.includes("_nottimage")) {
            const dataAttrs = imageData
                ? Object.entries(imageData)
                    .map(([k, v]) => `data-${k}="${v}"`)
                    .join(" ")
                : "";
            divImg = `<div class='tooltipimage ${imgTypes}' ${dataAttrs}></div>`;
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
            const dt_tr = this.game.getTr(dynamic);
            const label = _("Dynamic Assets");
            body = `
           <div class='tooltip-left'>${divImg}</div>
           <div class='tooltip-right'>
             <div class='tooltiptitle'>${name_tr}</div>
             <div class='tooltiptext'>${message_tr}</div>
             <div class='tooltiptext dynamic_tooltip' data-label='${label}'>${dt_tr}</div>
           </div>
    `;
        }
        return `<div class='${containerType}'>
        <div class='tooltip-body'>${body}</div>
    </div>`;
    }
    getTokenState(tokenId) {
        var tokenInfo = this.gamedatas.tokens[tokenId];
        return Number(tokenInfo?.state) || 0;
    }
    getTokenLocation(tokenId) {
        var tokenInfo = this.gamedatas.tokens[tokenId];
        return tokenInfo?.location;
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
    getTokenPresentaton(type, value, _args = {}, strict = false) {
        if (type.includes("_div"))
            return this.createTokenImage(value);
        if (value.includes("wicon"))
            return this.createTokenImage(value);
        const wicon = this.getRulesFor(value, "wicon", "");
        if (wicon)
            return this.createTokenImage(value, 0, wicon);
        if (this.getRulesFor(value, "type", "").includes("wicon"))
            return this.createTokenImage(value);
        if (strict) {
            const rules = this.getRulesFor(value, "*", null);
            if (rules === null)
                return null;
            if (rules.name)
                return this.game.getTr(rules.name);
            return null;
        }
        if (type == "reason" && value) {
            return "(" + this.getTokenName(value) + ")";
        }
        return "<b>" + this.getTokenName(value) + "</b>";
    }
    // override to generate dynamic tooltips and such
    updateTokenDisplayInfo(tokenDisplayInfo) { }
    ttSection(prefix, text) {
        if (prefix)
            return `<p><b>${prefix}:</b> ${text}</p>`;
        else
            return `<p>${text}</p>`;
    }
    iiSection(text) {
        return `<p><i>${text}</i></p>`;
    }
    /** One row in a stats tooltip table: icon | label | value. Omit `iconKey` to leave the icon cell blank. */
    ttRow(label, value, iconKey = "") {
        const icon = iconKey ? `<span class="wicon wicon_${iconKey}"></span>` : "";
        return `<tr><td>${icon}</td><td>${label}</td><td>${value}</td></tr>`;
    }
    /** Wrap `ttRow()` outputs in a stats table. Returns empty string if no rows. */
    ttStats(rows) {
        if (!rows)
            return "";
        return `<table class="tt_stats">${rows}</table>`;
    }
    createTokenImage(tokenId, state = 0, extraClass = "") {
        const span = document.createElement("span");
        span.id = tokenId + "_tt_" + this.globlog++;
        this.updateToken(span, { key: tokenId, location: "log", state });
        if (extraClass)
            span.classList.add("wicon", ...extraClass.split(/ +/));
        const name = this.getRulesFor(tokenId, "name", null);
        if (name)
            span.title = this.game.getTr(name);
        return span.outerHTML;
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
                // if adding key here and it ends with _name make sure also exclude from rtr in dbSetTokenLocation
                var keys = [
                    "token_name",
                    "token_name2",
                    "char_name",
                    "char_name2",
                    "token_divs",
                    "token_names",
                    "place_name",
                    "token_div",
                    "token2_div",
                    "token3_div",
                    "reason",
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
                    res = this.getTokenPresentaton(key, arg_value, args);
                    if (res)
                        args[key] = res;
                }
                if (args.sides) {
                    args.sides = this.replaceSimpleIconsInLog(args.sides);
                }
            }
            if (log && typeof log == "string") {
                log = gameui.clienttranslate_string(log);
                log = this.replaceSimpleIconsInLog(log, args);
            }
        }
        catch (e) {
            console.error(log, args, "Exception thrown", e.stack);
        }
        return { log, args };
    }
    replaceSimpleIconsInLog(log, args = {}) {
        if (!log || !log.includes("["))
            return log;
        return log.replace(/\[([^\]]+)\]/g, (match, keyExpr) => {
            try {
                const x = this.getTokenPresentaton(keyExpr, keyExpr, args, true);
                if (!x)
                    return match;
                return x;
            }
            catch (e) {
                console.error(`Failed to get token presentation for [${keyExpr}]`, e);
                return match;
            }
        });
    }
    async slideAndPlace(token, finalPlace, duration, delay = 0, mobileStyle, onEnd) {
        if (!$(token))
            console.error(`token not found for ${token}`);
        if ($(token)?.parentNode == $(finalPlace))
            return;
        if (this.gameAnimationsActive() == false) {
            duration = 0;
            delay = 0;
        }
        if (delay)
            await gameui.wait(delay);
        this.animationLa.phantomMove(token, finalPlace, duration, mobileStyle, onEnd);
        return gameui.wait(duration ?? 0);
    }
    showHiddenContent(id, title, selectedId, sort) {
        let dialog = new ebg.popindialog();
        dialog.create("pile");
        dialog.setTitle(title);
        const node = this.cloneAndFixIds(id, "_tt", true);
        node.removeAttribute("_lis");
        const cards_htm = node.innerHTML;
        const html = `
    <div id="card_pile_selector" class="card_pile_selector"></div>
    <div id="card_pile_help" class="card_pile_help">${_("Click on element below to see details")}</div>
    <div id="pile_content" class="pile_content">${cards_htm}</div>`;
        dialog.setContent(html);
        const parent = $("pile_content");
        let children = Array.from(parent.children).filter((node) => node.id && !node.id.startsWith("counter"));
        if (sort) {
            children.sort(sort);
            parent.replaceChildren(...children);
        }
        children.forEach((node, index) => {
            if (!node.id)
                return;
            const origId = node.id.replace("_tt", "");
            node.addEventListener("click", (e) => {
                const selected_html = this.getTooltipHtmlForToken(origId);
                $("card_pile_selector").innerHTML = selected_html;
            });
            if (index === selectedId)
                selectedId = origId;
        });
        if (children.length == 0) {
            $("card_pile_help").remove();
        }
        if (selectedId && typeof selectedId === "string") {
            const selected_html = this.getTooltipHtmlForToken(selectedId);
            $("card_pile_selector").innerHTML = selected_html;
        }
        dialog.show();
        return dialog;
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
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * Floating hand controls: lets the current player's hand either float (fixed at
 * the bottom of the screen, collapsible) or sit parked on the table where it was
 * originally placed. Mode + open/closed state persist in localStorage.
 *
 * Names mirror galacticcruise: `hand_area` is the element that moves; it docks
 * into the static `hand_wrapper` when floating and into the parked home on the
 * table when parked. `hand_wrapper` lives OUTSIDE the zoomed board (`thething`)
 * so `position: fixed` anchors to the viewport instead of the scaled board.
 * -----
 */
class LaHand {
    constructor(opts) {
        this.place = "floating";
        this.open = true;
        this.area = opts.handArea;
        this.parkedHome = opts.parkedHome;
        this.storagePrefix = opts.storagePrefix;
        this.floatDock = document.createElement("div");
        this.floatDock.id = "hand_wrapper";
        this.floatDock.className = "hand_wrapper";
        opts.floatDockParent.appendChild(this.floatDock);
    }
    get placeKey() {
        return `${this.storagePrefix}_hand_place`;
    }
    get openKey() {
        return `${this.storagePrefix}_hand_open`;
    }
    setup() {
        this.place = localStorage.getItem(this.placeKey) === "parked" ? "parked" : "floating";
        this.open = localStorage.getItem(this.openKey) !== "0";
        this.addControls();
        this.apply();
    }
    addControls() {
        this.area.insertAdjacentHTML("afterbegin", `<div class="hand_controls">
        <button id="button_hand_open" class="hand_button" title="${_("Click to open or close your hand")}">
          <i class="fa fa-arrow-circle-o-down icon_down"></i>
          <i class="fa fa-arrow-circle-o-up icon_up"></i>
        </button>
        <button id="button_hand_place" class="hand_button" title="${_("Click to float your hand or park it on the table")}">
          <i class="fa fa-hand-paper-o icon_float"></i>
          <i class="fa fa-window-maximize icon_park"></i>
        </button>
      </div>`);
        $("button_hand_place").addEventListener("click", () => this.setPlace(this.place === "floating" ? "parked" : "floating"));
        $("button_hand_open").addEventListener("click", () => this.setOpen(!this.open));
    }
    setPlace(place) {
        this.place = place;
        localStorage.setItem(this.placeKey, place);
        this.apply();
    }
    setOpen(open) {
        this.open = open;
        localStorage.setItem(this.openKey, open ? "1" : "0");
        this.apply();
    }
    apply() {
        if (this.place === "floating") {
            this.floatDock.appendChild(this.area);
        }
        else {
            this.parkedHome.appendChild(this.area);
        }
        this.area.dataset.place = this.place;
        this.area.dataset.open = this.open ? "1" : "0";
    }
}

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * Reusable board zoom controls: Fit / Zoom-in / Zoom-out buttons anchored to
 * the sticky action bar (#page-title). Uses CSS `zoom` to scale a target
 * element; persists mode + scale in localStorage.
 * -----
 */
class LaZoom {
    constructor(bga, opts) {
        this.bga = bga;
        this.mode = "fit";
        this.scale = 1;
        this.boundOnResize = () => {
            this.apply();
        };
        this.opts = {
            minScale: 0.3,
            maxScale: 4.0,
            stepFactor: 1.1,
            ...opts
        };
    }
    get modeKey() {
        return `${this.opts.storagePrefix}_board_zoom_mode`;
    }
    get scaleKey() {
        return `${this.opts.storagePrefix}_board_zoom_scale`;
    }
    setup() {
        this.destroyDivOtherCopies("board_layout_controls");
        const host = document.getElementById("page-title");
        host.insertAdjacentHTML("beforeend", `<div id="board_layout_controls" class="board_layout_controls">
        <button id="layout_home" class="layout_button active" title="${_("Fit to screen")}"><i class="fa6 fa6-arrows-to-dot"></i></button>
        <button id="layout_zoom_in" class="layout_button" title="${_("Zoom in")}"><i class="fa fa-search-plus"></i></button>
        <button id="layout_zoom_out" class="layout_button" title="${_("Zoom out")}"><i class="fa fa-search-minus"></i></button>
      </div>`);
        const savedMode = localStorage.getItem(this.modeKey);
        const savedScale = parseFloat(localStorage.getItem(this.scaleKey) ?? "");
        this.mode = savedMode === "manual" ? "manual" : "fit";
        this.scale = Number.isFinite(savedScale) && savedScale > 0 ? savedScale : 1;
        $("layout_home").addEventListener("click", () => this.setMode("fit"));
        $("layout_zoom_in").addEventListener("click", () => this.zoomByFactor(this.opts.stepFactor));
        $("layout_zoom_out").addEventListener("click", () => this.zoomByFactor(1 / this.opts.stepFactor));
        window.addEventListener("resize", this.boundOnResize);
        this.apply();
    }
    setMode(mode) {
        this.mode = mode;
        localStorage.setItem(this.modeKey, mode);
        this.apply();
    }
    zoomByFactor(factor) {
        const target = $(this.opts.targetId);
        const current = this.mode === "fit" ? parseFloat(target.dataset.scale ?? "1") || 1 : this.scale;
        const next = Math.min(this.opts.maxScale, Math.max(this.opts.minScale, current * factor));
        this.scale = next;
        localStorage.setItem(this.scaleKey, String(next));
        this.setMode("manual");
    }
    apply() {
        const target = $(this.opts.targetId);
        $("ebd-body").dataset.boardZoom = this.mode;
        document.querySelectorAll(".layout_button").forEach((btn) => btn.classList.remove("active"));
        if (this.mode === "fit") {
            $("layout_home")?.classList.add("active");
            this.applyFitZoom(target);
        }
        else {
            this.applyManualZoom(target);
        }
    }
    resetScale(target) {
        target.style.zoom = "";
        target.dataset.scale = "1";
        if (target.parentElement)
            target.parentElement.scrollLeft = 0;
    }
    applyFitZoom(target) {
        this.resetScale(target);
        const parent = target.parentElement;
        if (!parent)
            return;
        const availableWidth = parent.clientWidth;
        const naturalWidth = target.scrollWidth;
        if (naturalWidth <= availableWidth)
            return;
        this.applyZoom(target, availableWidth / naturalWidth);
    }
    applyManualZoom(target) {
        this.resetScale(target);
        this.applyZoom(target, this.scale);
        const wrap = target.parentElement;
        if (wrap && wrap.scrollWidth > wrap.clientWidth) {
            wrap.scrollLeft = (wrap.scrollWidth - wrap.clientWidth) / 2;
        }
    }
    applyZoom(target, scale) {
        target.dataset.scale = String(scale);
        target.style.zoom = String(scale);
    }
    // undo may duplicate the div; keep only the last
    destroyDivOtherCopies(id) {
        const panels = document.querySelectorAll("#" + id);
        panels.forEach((p, i) => {
            if (i < panels.length - 1)
                p.parentNode?.removeChild(p);
        });
    }
}

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */
const ERR_SILENT = 100;
/**  Generic processing related to Operation Machine */
class GameMachine {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }
    callfn(methodName, ...args) {
        if (this[methodName] !== undefined) {
            console.log("Calling " + methodName, args);
            return this[methodName](...args);
        }
        return undefined;
    }
    onEnteringStatePrivate(opInfo) {
        console.log("onEnteringStatePrivate", opInfo);
        if (!this.bga.players.isCurrentPlayerActive()) {
            if (opInfo?.description)
                this.bga.statusBar.setTitle(this.game.getTr(opInfo.description, opInfo));
            this.addUndoButton(opInfo?.ui?.undo); // opInfo not sanitized on this path
            return;
        }
        this.completeOpInfo(opInfo);
        this.opInfo = opInfo;
        const prompt = opInfo.prompt ? this.game.getTr(opInfo.prompt, opInfo) : "";
        let subprompt = "";
        if (opInfo.err && opInfo.q !== ERR_SILENT) {
            subprompt = _("Error") + ": " + this.game.getTr(opInfo.err, opInfo);
        }
        else if (opInfo.data?.reason) {
            subprompt = this.getReasonText(opInfo.data.reason);
        }
        if (subprompt && prompt) {
            this.bga.statusBar.setTitle(`[${subprompt}] ${prompt}`);
        }
        else if (prompt) {
            this.bga.statusBar.setTitle(prompt);
        }
        const multiselect = this.isMultiSelectArgs(opInfo);
        const singleTarget = opInfo.target.length == 1;
        const sortedTargets = Object.keys(opInfo.info);
        sortedTargets.sort((a, b) => opInfo.info[a].o - opInfo.info[b].o);
        for (const target of sortedTargets) {
            const paramInfo = opInfo.info[target];
            if (paramInfo.sec) {
                continue; // secondary buttons
            }
            const altTarget = paramInfo.tokenIdUi;
            const div = $(target) ?? $(altTarget);
            const q = paramInfo.q;
            const active = q == 0;
            // simple case we select element (dom node) which is target of operation
            if (div && active && paramInfo.noactive !== true) {
                const doNotShowActive = paramInfo.noactive ?? opInfo.ui.noactive ?? false;
                if (doNotShowActive == false) {
                    div.classList.add(this.game.classActiveSlot);
                    div.dataset.targetOpType = opInfo.type;
                }
            }
            // we also can have one addition way of selection (possibly)
            let altNode;
            if (opInfo.ui.replicate == true || paramInfo.replicate == true) {
                altNode = this.replicateTargetOnSelectionArea(target, paramInfo);
            }
            if (opInfo.ui.imagebuttons == true || paramInfo.imagebuttons == true) {
                altNode = this.replicateTargetOnToolbar(target, paramInfo);
            }
            if ((!altNode && (opInfo.ui.buttons || !div)) || paramInfo.buttons == true || (singleTarget && active)) {
                altNode = this.createTargetButton(target, paramInfo);
            }
            if (!altNode)
                continue;
            altNode.dataset.targetId = target;
            altNode.dataset.targetOpType = opInfo.type;
            if (!active) {
                altNode.title = this.game.getTr(paramInfo.err ?? _("Operation cannot be performed now"), paramInfo);
                altNode.classList.add(this.game.classButtonDisabled);
            }
            else {
                const title = paramInfo.tooltip;
                if (title)
                    altNode.title = this.game.getTr(title, paramInfo);
                else
                    this.game.updateTooltip(altTarget ?? target, altNode);
            }
            if (paramInfo.max !== undefined) {
                altNode.dataset.max = String(paramInfo.max);
            }
            else {
                altNode.dataset.max = "1";
            }
        }
        // secondary buttons
        for (const target of sortedTargets) {
            const paramInfo = opInfo.info[target];
            if (paramInfo.sec) {
                // skip, whatever
                const color = paramInfo.color ?? "secondary";
                const call = paramInfo.call ?? target;
                const button = this.bga.statusBar.addActionButton(this.getTargetButtonName(target, paramInfo), () => this.bga.actions.performAction(`action_${call}`, {
                    data: JSON.stringify({ target })
                }), {
                    color: color,
                    id: "button_" + target,
                    confirm: this.game.getTr(paramInfo.confirm)
                });
                button.dataset.targetId = target;
                if (paramInfo.q)
                    button.classList.add(this.game.classButtonDisabled);
            }
        }
        if (multiselect) {
            this.activateMultiSelectPrompt(opInfo);
        }
        if (opInfo.ui.buttons == false || opInfo.ui.replicate) {
            this.game.addShowMeButton(true);
        }
        if (opInfo.subtitle) {
            this.addInfoButton(this.game.getTr(opInfo.subtitle, opInfo));
        }
        // need a global condition when this can be added
        this.addUndoButton(this.bga.players.isCurrentPlayerActive() || opInfo.ui.undo);
    }
    createTargetButton(target, paramInfo) {
        const q = paramInfo.q;
        const active = q == 0;
        const color = paramInfo.color ?? this.opInfo?.ui.color ?? "primary";
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
        const color = paramInfo.color ?? this.opInfo?.ui.color ?? "secondary";
        let cloneHtml = this.createCustomTargetImageHtml(target, paramInfo);
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
        const altTarget = paramInfo.tokenIdUi;
        if (!altTarget)
            return undefined;
        let tokenNode = $(altTarget);
        if (tokenNode)
            return this.cloneForReplication(tokenNode);
        // No live token (e.g. gaining from the supply): materialize it just to grab its HTML.
        this.game.prepareToken(altTarget);
        tokenNode = $(altTarget);
        if (!tokenNode)
            return undefined;
        tokenNode.id = `${altTarget}_temp`;
        const html = tokenNode.outerHTML;
        tokenNode.remove(); // remove the temp node so it does not leak into the live DOM
        return html;
    }
    cloneForReplication(div) {
        const clone = div.cloneNode(true);
        clone.id = div.id + "_temp";
        clone.classList.remove(this.game.classActiveSlot);
        clone.classList.add(this.game.classActiveSlotHidden);
        const cloneHtml = clone.outerHTML;
        return cloneHtml;
    }
    createCustomTargetImageHtml(target, paramInfo) {
        let cloneHtml = this.createCustomButtonImageHtml(target, paramInfo);
        if (cloneHtml)
            return cloneHtml;
        const div = $(target);
        if (div) {
            return this.cloneForReplication(div);
        }
        return undefined;
    }
    replicateTargetOnSelectionArea(target, paramInfo) {
        let cloneHtml = this.createCustomTargetImageHtml(target, paramInfo);
        if (!cloneHtml)
            return;
        const parent = document.createElement("div");
        parent.classList.add("target_container");
        parent.innerHTML = cloneHtml;
        $("selection_area")?.appendChild(parent);
        const child = parent.children.item(0);
        child.classList.remove(this.game.classActiveSlot);
        child.classList.add(this.game.classActiveSlotHidden);
        child.addEventListener("click", (event) => this.onToken(event));
        return child;
    }
    getReasonText(reason) {
        if (!reason)
            return "";
        return this.game.getTokenName(reason);
    }
    getTargetButtonName(target, paramInfo) {
        const div = $(target);
        let name = paramInfo.name;
        if (!name && div) {
            name = div.dataset.name;
        }
        if (!name)
            return this.game.getTokenName(target);
        else
            return this.game.getTr(name, paramInfo.args ?? paramInfo);
    }
    isMultiSelectArgs(args) {
        return args.ttype == "token_count" || args.ttype == "token_array";
    }
    isMultiCountArgs(args) {
        return args.ttype == "token_count";
    }
    onLeavingState(args, isCurrentPlayerActive) {
        console.log("onLeavingState");
        this.game.removeAllClasses(this.game.classActiveSlot, this.game.classActiveSlotHidden);
        if (!this.bga.states.isOnClientState()) {
            this.game.removeAllClasses(this.game.classSelected, this.game.classSelectedAlt);
        }
        $("button_undo")?.remove();
        // remove children
        $("selection_area")?.replaceChildren();
    }
    /** default click processor */
    onToken(event, fromMethod) {
        console.log(event);
        let result = this.game.onClickSanity(event);
        if (!result.targetId) {
            return true;
        }
        if (!fromMethod)
            fromMethod = "onToken";
        event.stopPropagation();
        event.preventDefault();
        const ttype = this.opInfo?.ttype;
        if (!result.active) {
            return this.onToken_nonActive(result.targetId, result.targetNode);
        }
        if (ttype) {
            var methodName = "onToken_" + ttype;
            let ret = this.callfn(methodName, result.targetId, result.targetNode);
            if (ret === undefined)
                return false;
            return true;
        }
        console.error("no handler for ", ttype);
        return false;
    }
    onToken_nonActive(target, node) {
        return false;
    }
    onToken_token(target) {
        if (!target)
            return false;
        this.resolveAction({ target });
        return true;
    }
    onToken_token_array(target, node) {
        if (!this.opInfo)
            return false;
        return this.onMultiCount(target, this.opInfo, node);
    }
    onToken_token_count(target, node) {
        if (!this.opInfo)
            return false;
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
            const allSel = document.querySelectorAll(`.${this.game.classSelectedAlt},.${this.game.classSelected}`);
            allSel.forEach((node) => {
                delete node.dataset.count;
            });
            this.game.removeAllClasses(this.game.classSelected, this.game.classSelectedAlt);
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
            ?.then((x) => {
            console.log("action complete", x);
        })
            .catch((e) => {
            console.error(e);
        });
    }
    addInfoButton(helpText) {
        const escaped = document.createElement("div");
        escaped.textContent = helpText;
        const div = this.bga.statusBar.addActionButton(_("Info"), () => {
            this.game.showPopin(escaped.innerHTML);
        }, {
            color: "secondary",
            id: "button_info"
        });
        div.classList.add("button_info");
        div.title = _("Click to see additional information about this prompt");
    }
    addUndoButton(cond = true) {
        if (!$("button_undo") && !this.bga.players.isCurrentPlayerSpectator() && cond) {
            const div = this.bga.statusBar.addActionButton(_("Undo"), () => this.bga.actions
                .performAction("action_undo", [], {
                checkAction: false
            })
                .catch((e) => {
                console.error(e);
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
        const allSel = document.querySelectorAll(`.${this.game.classSelected}`);
        const selectedAlt = this.game.classSelectedAlt;
        this.game.removeAllClasses(selectedAlt);
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
        // Prefer the element whose id matches tid. clicknode may be a child element
        // of the real target (e.g. a monster inside a hex) and must not receive the
        // selection class.
        let node = $(tid) ?? clicknode;
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
            selNode.classList.remove(this.game.classSelected);
        }
        else {
            selNode.classList.add(this.game.classSelected);
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
                doneButton.classList.add(this.game.classButtonDisabled);
                doneButton.title = _("Cannot use this action because insuffient amount of elements selected");
            }
            else if (count > opInfo.count) {
                doneButton.classList.add(this.game.classButtonDisabled);
                doneButton.title = _("Cannot use this action because superfluous amount of elements selected");
            }
            else {
                doneButton.classList.remove(this.game.classButtonDisabled);
                doneButton.title = "";
            }
            doneButton.innerHTML = buttonName + ": " + count;
        }
        if (count > 0) {
            $(resetButtonId)?.classList.remove(this.game.classButtonDisabled);
            if (skipButton) {
                skipButton.classList.add(this.game.classButtonDisabled);
                skipButton.title = _("Cannot use this action because there are some elements selected");
            }
        }
        else {
            $(resetButtonId)?.classList.add(this.game.classButtonDisabled);
            if (skipButton) {
                skipButton.title = "";
                skipButton.classList.remove(this.game.classButtonDisabled);
            }
        }
    }
    completeOpInfo(opInfo) {
        var _a, _b, _c;
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
                (_a = opInfo.ui).replicate ?? (_a.replicate = true);
                (_b = opInfo.ui).color ?? (_b.color = "secondary");
            }
            else {
                (_c = opInfo.ui).color ?? (_c.color = "primary");
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
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */
class PlayerTurn extends GameMachine {
    constructor(game, bga) {
        super(game, bga);
    }
    onEnteringState(args, isCurrentPlayerActive) {
        if (args._private) {
            // merge private
            const priv = args._private;
            delete args._private;
            super.onEnteringStatePrivate({ ...args, ...priv });
        }
        else {
            super.onEnteringStatePrivate(args);
        }
    }
    onLeavingState(args, isCurrentPlayerActive) {
        super.onLeavingState(args);
    }
    onPlayerActivationChange(args, isCurrentPlayerActive) { }
    createCustomButtonImageHtml(target, paramInfo) {
        if (target.startsWith("action")) {
            const opKey = `Op_${target}`;
            const icon = this.game.getRulesFor(opKey, "wicon", "");
            const name = this.game.getTokenName(opKey);
            const q = paramInfo.q;
            const iconHtml = icon ? `<div class="wicon ${icon}"></div>` : "";
            return `<div id='${target}' class="fateaction err_${q}">${iconHtml}<span>${name}</span></div>`;
        }
        return super.createCustomButtonImageHtml(target, paramInfo);
    }
    onToken_nonActive(target, node) {
        if (!target)
            return false;
        if (!$(target))
            return false;
        const mainType = getPart(target, 0);
        switch (mainType) {
            case "card": {
                const container = $(target).parentElement?.id;
                if (container?.startsWith("discard") || container?.startsWith("deck")) {
                    this.game.showHiddenContent(container, _("Pile contents"), 0, function (a, b) {
                        const orderA = parseInt(a.dataset.state);
                        const orderB = parseInt(b.dataset.state);
                        return -orderA + orderB; // descending
                    });
                    return false;
                }
                break;
            }
        }
        return true;
    }
}

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */
class PlayerTurnConfirm {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }
    onEnteringState(args, isCurrentPlayerActive) {
        this.bga.statusBar.addActionButton(_("Confirm"), (event) => {
            this.bga.actions
                .performAction("action_resolve", {})
                .then((x) => {
                console.log("action complete", x);
            })
                .catch((e) => {
                console.error(e);
            });
        });
    }
}
class Game extends Game1Tokens {
    constructor(bga) {
        super(bga);
        //console.log("fate constructor");
        this.playerTurn = new PlayerTurn(this, bga);
        this.bga.states.register("PlayerTurn", this.playerTurn);
        this.bga.states.register("PlayerTurnConfirm", new PlayerTurnConfirm(this, bga));
    }
    onEnteringState(stateName, args) {
        console.log("Entering unknown state", stateName, args);
    }
    onToken(e) {
        // TODO: pick proper state object
        this.playerTurn.onToken(e);
    }
    setup(gamedatas) {
        this.inSetup = true;
        try {
            console.log("Starting game setup");
            super.setup(gamedatas);
            const title = $("page-title");
            const topbar = $("game_top_bar");
            if (topbar)
                topbar.remove();
            placeHtml(`
      <div id='game_top_bar' class='game_top_bar'>
        <div id='selection_area' class='selection_area'>
        </div>
      </div>`, title);
            placeHtml(`<div id="thething_wrap">        <div id="thething"></div>      </div>`, this.bga.gameArea.getElement());
            placeHtml(`<div id="limbo"></div>`, this.bga.gameArea.getElement());
            // Board area: map + monster turn display + supply (right side in wide layout)
            const mapWrapper = "map_wrapper";
            placeHtml(`<div id="board_area">
      <div id="display_battle"> </div>
      <div id="${mapWrapper}" class="map_wrapper"></div>
      <div id="display_monsterturn">
        <div id="deck_monster_yellow" class="deck deck_monster"></div>
        <div id="deck_monster_red" class="deck deck_monster"></div>
      </div>
      <div id="gsupply" class="gsupply">
        <div id="supply_monster" class="supply"></div>
        <div id="supply_crystal_green" class="supply bucket_crystal_green"></div>
        <div id="supply_crystal_red" class="supply bucket_crystal_red"></div>
        <div id="supply_crystal_yellow" class="supply bucket_crystal_yellow"></div>
        <div id="supply_die_attack" class="supply"></div>
        <div id="supply_die_monster" class="supply"></div>
      </div>
      <div id="timetrack_area"></div>
      </div>`, "thething");
            this.createMap($(mapWrapper));
            // Players panels
            placeHtml(`<div id="players_panels"></div>`, "thething");
            // Create hand container for current player only (not spectators)
            if (!this.bga.players.isCurrentPlayerSpectator()) {
                const myColor = this.player_color;
                const name = _("Hand");
                placeHtml(`<div id="hand_park_home"><div class="hand_area" data-name="${name}"><div id="hand_${myColor}" class="hand"></div></div></div>`, "players_panels");
            }
            const orderedPlayerIds = this.getOrderedPlayerIds(gamedatas);
            orderedPlayerIds.forEach((pid) => {
                const player = gamedatas.players[pid];
                const color = player.color;
                const hnoClass = player.heroNo ? `hno_${player.heroNo}` : "";
                const heroName = player.heroNo ? this.getTokenName(`hero_${player.heroNo}`) : "";
                placeHtml(`<div id="tableau_${color}" class="tableau ${hnoClass}"></div`, "players_panels");
                ["deck_ability", "deck_equip", "deck_event", "discard"].forEach((d) => {
                    const name = this.getTr(_("${hero}'s ${deck}"), {
                        hero: heroName,
                        deck: this.getRulesFor(d, "name")
                    });
                    placeHtml(`<div class="deck_wrapper" data-name="${name}"><div id="${d}_${color}" class="deck ${d}"></div></div>`, `tableau_${color}`);
                });
                const panel = this.bga.playerPanels.getElement(Number(player.id));
                placeHtml(`<div id="miniboard_${color}" class="miniboard ${hnoClass}" style="--player-color: #${color}">
          <div class="miniboard_banner">${heroName}</div>
          <div id="bucket_crystal_yellow_tableau_${color}" class="pboard_slot bucket bucket_crystal_yellow"></div>
        </div>`, panel);
                placeHtml(`
        <div id="pboard_${color}" class="pboard">
          <div id="aslot_${color}_actionMove" class="pboard_slot aslot aslot_actionMove"></div>
          <div id="aslot_${color}_actionAttack" class="pboard_slot aslot aslot_actionAttack"></div>
          <div id="aslot_${color}_actionPrepare" class="pboard_slot aslot aslot_actionPrepare"></div>
          <div id="aslot_${color}_actionFocus" class="pboard_slot aslot aslot_actionFocus"></div>
          <div id="aslot_${color}_actionMend" class="pboard_slot aslot aslot_actionMend"></div>
          <div id="aslot_${color}_actionPractice" class="pboard_slot aslot aslot_actionPractice"></div>
          <div id="aslot_${color}_empty_1" class="pboard_slot aslot aslot_empty"></div>
          <div id="aslot_${color}_empty_2" class="pboard_slot aslot aslot_empty"></div>
        </div>`, `limbo`);
            });
            this.setupTokens(gamedatas);
            this.zoomControls = new LaZoom(this.bga, { targetId: "thething", storagePrefix: "fate" });
            this.zoomControls.setup();
            const handArea = document.querySelector("#hand_park_home > .hand_area");
            if (handArea) {
                this.handControls = new LaHand({
                    handArea,
                    parkedHome: $("hand_park_home"),
                    floatDockParent: this.bga.gameArea.getElement(),
                    storagePrefix: "fate"
                });
                this.handControls.setup();
            }
            this.setupNotifications();
            this.setupCardCatalog();
            if (gamedatas.endBanner) {
                if (gamedatas.endBanner.isWellDestroyed)
                    this.bga.gameArea.addLastTurnBanner(gamedatas.endBanner.message);
                else
                    this.bga.gameArea.addWinConditionBanner(gamedatas.endBanner.message);
            }
            // last minute tweaks for miniboard
            Object.values(gamedatas.players).forEach((player) => {
                const color = player.color;
                // attach hand counter to miniboard
                const mini = $(`miniboard_${color}`);
                const handCounter = $(`counter_hand_${color}`);
                if (handCounter && mini) {
                    mini.appendChild(handCounter);
                    handCounter.classList.add("counter_hand", "wicon_hand", "wicon");
                    const handMaxCounter = $(`tracker_hand_${color}`);
                    if (handMaxCounter)
                        handCounter.dataset.limit = handMaxCounter.dataset.state;
                }
                // hero damage mirror on miniboard
                const heroNo = player.heroNo;
                // damage
                const srcBucket = $(`bucket_crystal_red_hero_${heroNo}`);
                const initialState = srcBucket?.dataset.state ?? "0";
                placeHtml(`<div id="tracker_damage_${color}" class="bucket bucket_crystal_red tracker_damage" data-hero="hero_${heroNo}" data-state="${initialState}"></div>`, `miniboard_${color}`);
                // Boldur's hero cards grant "Armor. (Always prevents 1 damage)" - static signature pill
                if (player.heroNo == 4)
                    placeHtml(`<div id="tracker_armor_${color}" class="tracker_armor tracker wicon wicon_armor" data-state="1"></div>`, `miniboard_${color}`);
                // marker tracking cost of upgrade
                const marker = $(`marker_${color}_3`);
                $(`miniboard_${color}`).appendChild(marker);
                marker.classList.add("bucket", "upgrade_cost");
                this.updateTooltip("upgrade_cost", marker);
                const tname = this.getRulesFor(`hero_${player.heroNo}`, "name");
                $(`tableau_${color}`).dataset.name = this.getTr(tname);
                const hand = document.querySelector(`.hand_area > #hand_${color}`);
                if (hand)
                    hand.parentElement.dataset.name = this.getTr("${hero}'s Hand", { hero: this.getTr(tname) });
            });
        }
        catch (e) {
            console.error(e);
            throw e;
        }
        finally {
            this.inSetup = false;
        }
        console.log("Ending game setup");
    }
    setupCardCatalog() {
        const root = this.bga.gameArea.getElement();
        placeHtml(`<div id="catalog" class="catalog"></div>`, root);
        const folders = [];
        for (let hno = 1; hno <= 4; hno++) {
            const heroName = this.getRulesFor(`hero_${hno}`, "name", `Hero ${hno}`);
            folders.push({ id: `catalog_hero_${hno}`, titleKey: _("Cards:") + " " + heroName, hno });
        }
        folders.push({ id: "catalog_monster", titleKey: _("Cards: Monsters") });
        folders.forEach((f) => {
            placeHtml(`<details class="catalog_folder" id="${f.id}">
          <summary><span class="catalog_title">${this.getTr(f.titleKey)}</span></summary>
          <div class="catalog_filter_wrap">
            <input type="text" class="catalog_filter" placeholder="${_("Search...")}">
            <button type="button" class="catalog_filter_clear" title="${_("Clear")}">✕</button>
          </div>
          <span>${_("Click or hover over card to get details")}</span>
          <div class="catalog_cards" id="${f.id}_cards"></div>
        </details>`, "catalog");
        });
        const ctypeOrder = { hero: 0, ability: 1, equip: 2, event: 3, monster: 4 };
        const entries = [];
        for (const key in this.gamedatas.token_types) {
            if (!key.startsWith("card_"))
                continue;
            const ctype = getPart(key, 1);
            if (ctype === "monster") {
                const num = parseInt(getPart(key, 2)) || 0;
                entries.push({ cardId: key, folderId: "catalog_monster", ctype, num });
            }
            else {
                const hno = parseInt(getPart(key, 2));
                const num = parseInt(getPart(key, 3)) || 0;
                if (!(hno >= 1 && hno <= 4))
                    continue;
                entries.push({ cardId: key, folderId: `catalog_hero_${hno}`, ctype, num });
            }
        }
        entries.sort((a, b) => (ctypeOrder[a.ctype] ?? 99) - (ctypeOrder[b.ctype] ?? 99) || a.num - b.num);
        const counts = {};
        entries.forEach((e) => {
            const container = $(`${e.folderId}_cards`);
            if (!container)
                return;
            const info = this.getTokenDisplayInfo(e.cardId);
            const div = document.createElement("div");
            div.id = `catalog_${e.cardId}`;
            div.classList.add(...info.imageTypes.split(/  */).filter(Boolean));
            div.classList.add("catalog_card");
            div.dataset.targetId = e.cardId;
            container.appendChild(div);
            this.updateTooltip(e.cardId, div);
            counts[e.folderId] = (counts[e.folderId] ?? 0) + 1;
        });
        folders.forEach((f) => {
            const folderEl = $(f.id);
            if (!folderEl)
                return;
            const titleSpan = folderEl.querySelector(".catalog_title");
            const count = counts[f.id] ?? 0;
            titleSpan.textContent = `${titleSpan.textContent} (${count})`;
            const cardsEl = folderEl.querySelector(".catalog_cards");
            cardsEl.addEventListener("click", (e) => {
                const node = e.target.closest(".catalog_card");
                if (!node || !node.dataset.targetId)
                    return;
                this.showCatalogCardDialog(node.dataset.targetId);
            });
            const filter = folderEl.querySelector(".catalog_filter");
            const applyFilter = () => {
                const q = filter.value.trim().toLowerCase();
                cardsEl.querySelectorAll(".catalog_card").forEach((card) => {
                    if (!q) {
                        card.style.removeProperty("display");
                        return;
                    }
                    const id = card.dataset.targetId;
                    if (!id)
                        return;
                    const text = (this.getTooltipHtmlForToken(id) || "").toLowerCase() + " " + (card.dataset.name || "").toLowerCase();
                    card.style.display = text.includes(q) ? "" : "none";
                });
            };
            filter.addEventListener("input", applyFilter);
            const clearBtn = folderEl.querySelector(".catalog_filter_clear");
            clearBtn.addEventListener("click", () => {
                filter.value = "";
                applyFilter();
                filter.focus();
            });
        });
    }
    showCatalogCardDialog(cardId) {
        const dialog = new ebg.popindialog();
        dialog.create("catalog_card_dlg");
        dialog.setTitle(this.getTokenName(cardId));
        dialog.setContent(`<div class="catalog_dlg_content">${this.getTooltipHtmlForToken(cardId)}</div>`);
        dialog.show();
    }
    /** Populate timetrack slots inside the timetrack container (created by token system). */
    createTimetrack(trackId) {
        // Build slots programmatically from material data
        for (let step = 1; step <= 20; step++) {
            const slotId = `slot_${trackId}_${step}`;
            if ($(slotId))
                continue;
            const spotType = this.getRulesFor(slotId, "r", null);
            if (spotType === null)
                break;
            const phase = spotType.startsWith("tm_yellow") ? "tm_phase_yellow" : "tm_phase_red";
            placeHtml(`<div id="${slotId}" class="tt_slot ${spotType} ${phase}" data-step="${step}"></div>`, trackId);
            this.updateTooltip(slotId);
        }
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
                const road = this.getRulesFor(hexId, "road", 0);
                const roadCls = road ? " road" : "";
                hexes.push(`<div class="hex terrain_${terrain}${roadCls}" id="${hexId}" style="left:${leftPct}%;top:${topPct}%;" data-q="${q}" data-r="${r}" data-loc="${loc}"></div>`);
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
        const result = { ...tokenInfo };
        const loc = tokenInfo.location;
        const tokenKey = tokenInfo.key;
        const mainType = getPart(tokenKey, 0);
        if (args.place_from)
            result.place_from = args.place_from;
        switch (mainType) {
            case "tracker":
                // Redirect tracker tokens to miniboard in player panel
                if (loc.startsWith("tableau_")) {
                    const color = getPart(loc, 1);
                    result.location = `miniboard_${color}`;
                    result.noa = true;
                    if (tokenKey.startsWith("tracker_hand")) {
                        const handCounter = $(`counter_hand_${color}`);
                        if (handCounter)
                            handCounter.dataset.limit = String(tokenInfo.state);
                    }
                    // Hero attack/health changed (upgrade, equip) — refresh its stats box on the map
                    if (tokenKey.startsWith("tracker_strength") || tokenKey.startsWith("tracker_health")) {
                        const player = Object.values(this.gamedatas.players ?? {}).find((p) => p.color === color);
                        if (player)
                            result.onEnd = () => this.refreshAttackStat(`hero_${player.heroNo}`);
                    }
                }
                break;
            case "marker":
                if (loc.startsWith("tableau_")) {
                    const color = getPart(loc, 1);
                    result.location = `miniboard_${color}`;
                    result.noa = true;
                }
                break;
            case "monster":
                if (loc === "supply_monster") {
                    // Stack monsters by type in supply: create sub-container per monster type
                    const monsterType = getPart(tokenKey, 0) + "_" + getPart(tokenKey, 1);
                    if (monsterType === "monster_legend") {
                        // Legends: place directly on map_wrapper, no piling
                        result.location = "map_wrapper";
                    }
                    else {
                        const subId = "supply_" + monsterType;
                        if (!$(subId)) {
                            const attackHtml = this.buildAttackStat(monsterType);
                            const stats = attackHtml ? `<div class="stats_attack">${attackHtml}</div>` : "";
                            placeHtml(`<div id="${subId}" class="pile_monster ${monsterType}">${stats}</div>`, "map_wrapper");
                        }
                        result.location = subId;
                    }
                    // Shrink & fade at current position, then snap to supply
                    if ($(tokenKey)?.parentElement?.id?.startsWith("hex_")) {
                        result.noa = true;
                        result.onStart = async (node) => {
                            await this.animationLa.shrinkAndFade(node);
                        };
                    }
                }
                break;
            case "card":
                result.onClick = (e) => this.onToken(e);
                // Upgrade flip: L2 just landed in tableau; flip from L1's sprite to L2's at the slot.
                if (args.flip_from) {
                    const fromId = args.flip_from;
                    result.onEnd = () => {
                        this.animationLa.cardFlip(tokenKey, String(tokenInfo.state), 1000, undefined, fromId);
                    };
                }
                break;
            case "crystal": {
                // Bucket redirect: tokens placed on another token get a sub-container bucket
                // e.g. crystal_red on monster_goblin_1 → bucket_crystal_red_monster_goblin_1
                const bucketType = getPart(tokenKey, 0) + "_" + getPart(tokenKey, 1);
                const tokenNode = $(tokenKey);
                const oldBucket = tokenNode?.parentElement;
                const oldBucketId = oldBucket?.classList.contains("bucket") ? oldBucket.id : null;
                if (!loc.startsWith("supply")) {
                    const bucketId = `bucket_${bucketType}_${loc}`;
                    if (!$(bucketId)) {
                        placeHtml(`<div id="${bucketId}" class="bucket bucket_${bucketType}"></div>`, loc);
                    }
                    result.location = bucketId;
                    // Crystal landing on a monster, hero, or card: suppress slide, pulse the crystal bucket instead
                    if (loc.startsWith("monster") || loc.startsWith("hero") || loc.startsWith("card")) {
                        result.noa = true;
                        result.onEnd = () => {
                            if (oldBucketId)
                                this.updateBucketCount(oldBucketId);
                            this.updateBucketCount(bucketId);
                            this.animationLa.pulse(bucketId);
                            this.updateTooltip(loc, undefined, { force: true });
                        };
                    }
                    else {
                        result.onEnd = () => {
                            if (oldBucketId) {
                                this.updateBucketCount(oldBucketId);
                                const oldCharId = oldBucketId.replace(/^bucket_crystal_[a-z]+_/, "");
                                if ($(oldCharId))
                                    this.updateTooltip(oldCharId, undefined, { force: true });
                            }
                            this.updateBucketCount(bucketId);
                        };
                    }
                }
                else if (oldBucketId) {
                    // Crystal returning to supply — suppress slide, just pulse the old bucket
                    const oldCharId = oldBucketId.replace(/^bucket_crystal_[a-z]+_/, "");
                    result.noa = true;
                    result.onEnd = () => {
                        this.updateBucketCount(oldBucketId);
                        this.animationLa.pulse(oldBucketId);
                        if ($(oldCharId))
                            this.updateTooltip(oldCharId, undefined, { force: true });
                    };
                }
                break;
            }
            case "timetrack":
                // Redirect timetrack container to timetrack_area and populate slots
                result.location = "timetrack_area";
                result.onEnd = () => this.createTimetrack(tokenKey);
                break;
            case "rune":
                // Redirect rune_stone to the specific timetrack slot based on its state (step number)
                if (tokenKey === "rune_stone" && loc.startsWith("timetrack_")) {
                    result.location = `slot_${loc}_${tokenInfo.state}`;
                }
                break;
            case "die":
                // Dice landing on display_battle: show evaporate effect at the attack target
                if (loc === "display_battle" && args.anim_target) {
                    const target = args.anim_target;
                    result.onEnd = (node) => {
                        this.animationLa.evaporate(node, target);
                    };
                }
                break;
            case "display":
                if (tokenKey.startsWith("display_battle")) {
                    result.nop = true;
                }
                break;
            case "tableau":
                result.nop = true;
                break;
        }
        return result;
    }
    /** Update data-state on a bucket by counting its direct children (excluding other buckets). */
    updateBucketCount(bucketId) {
        const bucket = $(bucketId);
        if (bucket) {
            let count = 0;
            for (let i = 0; i < bucket.children.length; i++) {
                if (!bucket.children[i].classList.contains("bucket"))
                    count++;
            }
            bucket.dataset.state = String(count);
            // sync miniboard damage mirror if this is a hero's red crystal bucket
            const heroMatch = bucketId.match(/^bucket_crystal_red_(hero_(\d+))$/);
            if (heroMatch) {
                const heroNo = parseInt(heroMatch[2]);
                const player = Object.values(this.gamedatas.players).find((p) => p.heroNo === heroNo);
                const max = player ? this.getTokenState(`tracker_health_${player.color}`) : 0;
                const mirror = document.querySelector(`[data-hero="${heroMatch[1]}"].bucket_crystal_red`);
                if (mirror)
                    mirror.dataset.state = String(count);
                if (max > 0) {
                    bucket.dataset.max = String(max);
                    if (mirror)
                        mirror.dataset.max = String(max);
                }
            }
            // Monster damage bucket: stash max HP so the badge can render "damage/max".
            const monsterMatch = bucketId.match(/^bucket_crystal_red_(monster_.+)$/);
            if (monsterMatch) {
                const max = parseInt(this.getRulesFor(monsterMatch[1], "health", "0"));
                if (max > 0)
                    bucket.dataset.max = String(max);
                // refresh the attack box for monsters whose attack depends on damage (e.g. Nidhuggr)
                this.refreshAttackStat(monsterMatch[1]);
            }
        }
    }
    /** Inner html for a character/pile .stats_attack box: attack / max-health.
     *  Returns null for non-combatants (no stats, e.g. gold veins) so no box is shown. */
    buildAttackStat(charId) {
        let attack = 0;
        let hp = 0;
        const damage = parseInt($(`bucket_crystal_red_${charId}`)?.dataset.state ?? "0");
        if (getPart(charId, 0) === "hero") {
            const heroNo = getIntPart(charId, 1);
            const player = Object.values(this.gamedatas.players).find((p) => p.heroNo === heroNo);
            if (player) {
                attack = this.getTokenState(`tracker_strength_${player.color}`);
                hp = this.getTokenState(`tracker_health_${player.color}`);
            }
        }
        else {
            hp = parseInt(this.getRulesFor(charId, "health", "0"));
            if (getPart(charId, 1) === "legend" && getPart(charId, 2) === "6") {
                // Nidhuggr: attack equals its current remaining health (max - damage)
                attack = hp - damage;
            }
            else {
                attack = parseInt(this.getRulesFor(charId, "strength", "0"));
            }
        }
        if (!attack && !hp)
            return null;
        const remhealth = hp - damage;
        const important = damage > 0;
        return this.buildCompositeCounter({
            [`counter_attack_${charId}`]: attack,
            [`counter_remhealth_${charId}`]: remhealth
        }, important ? "composite_second_important" : "");
    }
    buildCompositeCounter(pair, classes = "") {
        const [[firstId, firstValue], [secondId, secondValue]] = Object.entries(pair);
        return `<div class="composite ${classes}"><div id="${firstId}" class="composite_first">${firstValue}</div><span class="composite_separator"></span><div id="${secondId}" class="composite_second">${secondValue}</div></div>`;
    }
    /** Ensure a map character carries its .stats_attack box and refresh its content. */
    refreshAttackStat(charId) {
        const node = $(charId);
        if (!node)
            return;
        let box = node.querySelector(":scope > .stats_attack");
        const html = this.buildAttackStat(charId);
        if (html === null) {
            box?.remove();
            return;
        }
        if (!box) {
            box = document.createElement("div");
            box.className = "stats_attack";
            node.appendChild(box);
        }
        box.innerHTML = html;
    }
    updateToken(tokenNode, placeInfo) {
        super.updateToken(tokenNode, placeInfo);
        const charId = placeInfo.key;
        const mainType = getPart(charId, 0);
        if (mainType === "hero") {
            this.refreshAttackStat(charId);
        }
        else if (mainType === "monster") {
            const loc = placeInfo.location ?? "";
            // only map characters get a box; supply monsters live in piles (the pile has its own)
            if (loc.startsWith("hex_") || loc === "map_wrapper")
                this.refreshAttackStat(charId);
        }
    }
    getTokenPresentaton(type, tokenKey, args = {}, strict = false) {
        const res = strict ? super.getTokenPresentaton(type, tokenKey, args, true) : super.getTokenPresentaton(type, tokenKey, args);
        if (res === null)
            return null;
        const tc = this.getRulesFor(tokenKey, "tc");
        if (tc)
            return `<span style="color:${tc};font-weight:bold">${res}</span>`;
        return res;
    }
    updateTokenDisplayInfo(tokenInfo) {
        // override to generate dynamic tooltips and such
        const mainType = tokenInfo.mainType;
        const tokenId = tokenInfo.tokenId;
        const subType = getPart(tokenId, 1);
        switch (mainType) {
            case "card": {
                if (subType === "monster") {
                    const spawnLoc = this.getTokenName(tokenInfo.spawnloc);
                    tokenInfo.tooltip = this.ttSection(_("Spawn Location"), spawnLoc);
                    if (tokenInfo.ftext)
                        tokenInfo.tooltip += this.ttSection(_("Flavor"), tokenInfo.ftext);
                }
                else if (["hero", "ability", "equip", "event"].includes(subType)) {
                    const heroName = this.getTokenName(`hero_${tokenInfo.hno}`);
                    tokenInfo.tooltip = this.ttSection(_("Hero"), heroName);
                    tokenInfo.tooltip += this.ttSection(_("Type"), this.getTokenName(`ctype_${subType}`));
                    if (tokenInfo.quest)
                        tokenInfo.tooltip += this.ttSection(_("Quest"), this.getTr(tokenInfo.quest));
                    if (tokenInfo.effect)
                        tokenInfo.tooltip += this.ttSection(_("Effect"), this.getTr(tokenInfo.effect));
                    if (tokenInfo.flavour)
                        tokenInfo.tooltip += this.iiSection(this.getTr(tokenInfo.flavour));
                }
                break;
            }
            case "monster": {
                if (subType === "goldvein") {
                    tokenInfo.tooltip = this.ttSection(_("Effect"), _("Mountain gold deposit. Damage dealt converts to [XP]."));
                    break;
                }
                if (subType === "legend") {
                    this.buildLegendTooltip(tokenInfo);
                }
                else {
                    this.buildMonsterTooltip(tokenInfo);
                }
                break;
            }
            case "hero": {
                const heroNo = parseInt(getPart(tokenId, 1));
                const player = Object.values(this.gamedatas.players).find((p) => p.heroNo === heroNo);
                tokenInfo.tooltip = "";
                if (!player)
                    break;
                const color = player.color;
                let rows = "";
                rows += this.ttRow(_("Strength"), this.getTokenState(`tracker_strength_${color}`), "strength");
                rows += this.ttRow(_("Health"), this.getTokenState(`tracker_health_${color}`), "health");
                rows += this.ttRow(_("Range"), this.getTokenState(`tracker_range_${color}`), "range");
                rows += this.ttRow(_("Move"), this.getTokenState(`tracker_move_${color}`), "move");
                rows += this.ttRow(_("Hand Limit"), this.getTokenState(`tracker_hand_${color}`), "hand");
                tokenInfo.tooltip += this.ttStats(rows);
                break;
            }
            case "house": {
                tokenInfo.tooltip = this.ttSection(_("Type"), this.getTr(tokenInfo.name));
                break;
            }
            case "die": {
                const dtype = getPart(tokenId, 1); // "attack" or "monster"
                const dieState = this.getTokenState(tokenId);
                if (dieState >= 1 && dieState <= 6) {
                    tokenInfo.imageData = { state: String(dieState) };
                    const sideKey = `side_die_${dtype}_${dieState}`;
                    const sideInfo = this.getRulesFor(sideKey, "name", "");
                    if (sideInfo)
                        tokenInfo.tooltip = this.ttSection(_("Result"), this.getTr(sideInfo));
                }
                break;
            }
            case "slot": {
                if (tokenId.startsWith("slot_timetrack")) {
                    // Timetrack slots: show step number and effect name
                    const spotType = this.getRulesFor(tokenId, "r", null);
                    if (spotType) {
                        const stepNum = getPart(tokenId, -1);
                        const effectName = this.getTokenName(spotType);
                        tokenInfo.name = `${_("Step")} ${stepNum}: ${effectName}`;
                        const spotDescriptions = {
                            tm_yellow_axes: _("Reinforcements: each player draws 1 yellow monster card and places monsters accordingly"),
                            tm_red_axes: _("Reinforcements: each player draws 1 red monster card and places monsters accordingly"),
                            tm_yellow_shield: _("No reinforcements and no charge this turn"),
                            tm_red_shield: _("No reinforcements and no charge this turn"),
                            tm_red_skull: _("Charge: all monsters move 1 additional area toward Grimheim")
                        };
                        tokenInfo.tooltip = this.ttSection(_("Effect"), spotDescriptions[spotType] ?? effectName);
                    }
                }
                break;
            }
            case "hex": {
                const q = getPart(tokenId, 1);
                const r = getPart(tokenId, 2);
                if (!r)
                    return;
                tokenInfo.imageTypes = "_nottimage";
                const locname = this.getTokenName(tokenInfo.loc);
                const areacolor = this.getTokenName(tokenInfo.c);
                const terrainname = this.getTokenName(tokenInfo.terrain);
                const coords = `(${r},${q})`;
                tokenInfo.name = `${terrainname} ${coords}`;
                tokenInfo.tooltip = this.ttSection(_("Terrain"), terrainname);
                tokenInfo.tooltip += this.ttSection(_("Coords"), `(${r},${q})`);
                if (tokenInfo.loc) {
                    tokenInfo.name = `${locname} - ${terrainname} ${coords}`;
                    tokenInfo.tooltip += this.ttSection(_("Location"), locname);
                }
                if (tokenInfo.c)
                    tokenInfo.tooltip += this.ttSection(_("Location Color"), areacolor);
                if (tokenInfo.road)
                    tokenInfo.tooltip += this.ttSection(_("Road"), _("Yes"));
            }
        }
    }
    factionAttackRange(faction) {
        return faction === "firehorde" ? 2 : 1;
    }
    factionEffectText(faction) {
        const map = {
            trollkin: _("All Trollkin get +1 attack strength for each other Trollkin adjacent to them."),
            firehorde: _("All Fire Horde monsters have attack range 2."),
            dead: _("Runes count as hits when the Dead attack.")
        };
        return map[faction];
    }
    factionFlavorText(faction) {
        const map = {
            trollkin: _("The Trollkin are a savage clan of goblins, brutes, and trolls that roam the forests and valleys."),
            firehorde: _("The Fire Horde emerges from volcanic regions, bringing sprites, elementals, and mighty Jotunns."),
            dead: _("The Dead rise from marshes and plains – imps, skeletons, and the fearsome Draugr.")
        };
        return map[faction];
    }
    buildMonsterTooltip(tokenInfo) {
        let rows = "";
        tokenInfo.tooltip = this.ttSection(_("Faction"), this.getTokenName(tokenInfo.faction) + " - " + tokenInfo.rank);
        rows += this.ttRow(_("Strength"), tokenInfo.strength, "strength");
        rows += this.ttRow(_("Health"), tokenInfo.health, "health");
        rows += this.ttRow(_("Gold"), tokenInfo.xp, "gold");
        if (tokenInfo.move)
            rows += this.ttRow(_("Move"), tokenInfo.move, "move");
        // Range is not shipped in material; derive from faction. Only show when > 1.
        const range = this.factionAttackRange(tokenInfo.faction);
        if (range > 1)
            rows += this.ttRow(_("Range"), String(range), "range");
        if (tokenInfo.armor)
            rows += this.ttRow(_("Armor"), tokenInfo.armor);
        tokenInfo.tooltip += this.ttStats(rows);
        const eff = this.factionEffectText(tokenInfo.faction);
        if (eff)
            tokenInfo.tooltip += this.ttSection(_("Faction Effect"), eff);
        const flv = this.factionFlavorText(tokenInfo.faction);
        if (flv)
            tokenInfo.tooltip += this.iiSection(flv);
    }
    buildLegendTooltip(tokenInfo) {
        const tokenId = tokenInfo.tokenId;
        const legendNum = getPart(tokenId, 2);
        const level = getPart(tokenId, 3); // "1" or "2"
        // Add parent prefix classes for CSS sprite targeting (create=1 tokens don't get these automatically)
        tokenInfo.imageTypes += ` monster_legend monster_legend_${legendNum}`;
        // Look up both sides' stats
        const side1 = this.getAllRules(`monster_legend_${legendNum}_1`);
        const side2 = this.getAllRules(`monster_legend_${legendNum}_2`);
        const legendFlavor = {
            "1": _("A chilling sight to behold, Hel brings the dead to the underworld at death. At least those who died of old age and sickness. Let's hope that's not you..."),
            "2": _("This unsettling figure may be blind, but still sees things of the past and future, acting as an advisor to the Asgaard gods. In this case Loki and his hordes."),
            "3": _("The strength of this colossal beast is matched only by his lack of intellect. He has heard the singing from the mead hall and can't bear it any longer. He is hungry..."),
            "4": _("The fire giant with his flaming sword is supposed to bring about Ragnarok, the apocalypse of the cosmos - if he makes it that long."),
            "5": _("This brute leader is fearless and collects battle scars as trophies of his invincibility. Naturally, his presence infuses the entire trollkin clan with confidence."),
            "6": _("While the actual Midgaard Serpent encircles the entire world tree, Yggdrasil, nobody really has time to compare the sizes when this beast approaches.")
        };
        tokenInfo.tooltip = this.ttSection(_("Faction"), this.getTokenName(tokenInfo.faction) + " " + _("Legend") + " " + (level === "1" ? "I" : "II"));
        let rows = "";
        // Stats as Level I / Level II
        if (side1 && side2) {
            const fmt = (v) => (v == 0 ? "*" : `${v ?? "-"}`);
            const dual = (label, field, icon = "") => {
                const v1 = level === "1" ? side1[field] : side2[field];
                const v2 = side2[field];
                if (v1 || v2) {
                    rows += this.ttRow(label, v1 == v2 ? fmt(v1) : `${fmt(v1)} (${fmt(v2)} - level II)`, icon);
                }
            };
            dual(_("Strength"), "strength", "strength");
            dual(_("Health"), "health", "health");
            dual(_("Gold"), "xp", "gold");
            // Range follows the legend's faction (firehorde legends get +1).
            const range = this.factionAttackRange(tokenInfo.faction);
            if (range > 1)
                rows += this.ttRow(_("Range"), String(range), "range");
            dual(_("Armor"), "armor");
        }
        tokenInfo.tooltip += this.ttStats(rows);
        // Special ability notes for legends with * strength
        const specialAbility = {
            "2": _("As her attack, deals 1 unpreventable damage to all heroes everywhere."),
            "6": _("Wyrm: Nidhuggr's strength is the same as its remaining health.")
        };
        if (specialAbility[legendNum])
            tokenInfo.tooltip += this.ttSection(_("Ability"), specialAbility[legendNum]);
        // Faction effect — same map as regular monsters; lets the player see Seer/Surt are firehorde, Queen is dead, etc.
        const eff = this.factionEffectText(tokenInfo.faction);
        if (eff)
            tokenInfo.tooltip += this.ttSection(_("Faction Effect"), eff);
        if (legendFlavor[legendNum])
            tokenInfo.tooltip += this.iiSection(legendFlavor[legendNum]);
    }
    /** Get crystal damage/gold/mana + status (stun) info for a character from its child tokens. */
    getCrystalInfo(tokenId) {
        const iconForCrystal = { red: "damage", green: "mana", yellow: "gold" };
        let rows = "";
        for (const type of ["red", "green", "yellow"]) {
            const bucket = $(`bucket_crystal_${type}_${tokenId}`);
            const count = parseInt(bucket?.dataset.state ?? "0");
            if (count > 0)
                rows += this.ttRow(this.getTokenName(`crystal_${type}`), count, iconForCrystal[type]);
        }
        let info = this.ttStats(rows);
        if ($(tokenId)?.querySelector(':scope > .stunmarker[data-state="0"]')) {
            info += this.ttSection(_("Stunned"), _("Cannot move during this monster turn"));
        }
        return info;
    }
    getDynamicTooltip(tokenInfo, attachNode) {
        if (attachNode) {
            const crystalInfo = this.getCrystalInfo(attachNode?.id);
            return crystalInfo;
        }
        else {
            return undefined;
        }
    }
    handleStackedTooltips(attachNode) {
        // Case 1: A hex that has children — remove hex tooltip, children own it
        if (attachNode.classList.contains("hex")) {
            for (const child of attachNode.children) {
                this.handleStackedTooltipsParentChild(attachNode, child);
            }
            return;
        }
        // Case 2: A token (hero/monster/house) on a hex — combine hex + token tooltips on the token
        if (this.handleStackedTooltipsParentChild(attachNode.parentElement, attachNode)) {
            return;
        }
        // Case 3: Level I hero/ability card on tableau — append Level II preview as a second container
        const tokenId = attachNode.dataset.tt ?? attachNode.id;
        const siblingId = this.getLevel2Sibling(tokenId);
        if (siblingId) {
            const baseHtml = this.getTooltipHtmlForToken(tokenId);
            const reverseHtml = this.getTooltipHtmlForToken(siblingId);
            this.game.addTooltipHtml(attachNode.id, baseHtml + reverseHtml, this.game.defaultTooltipDelay);
        }
    }
    handleStackedTooltipsParentChild(parentElement, attachNode) {
        const parentId = parentElement?.id;
        const tokenId = attachNode.dataset.tt ?? attachNode.id;
        if (!tokenId)
            return false;
        const mainChildType = getPart(tokenId, 0);
        // buckets on hexes carry crystal-count tooltips already; don't stack hex info on top
        if (parentId?.startsWith("hex") && mainChildType != "bucket" && mainChildType != "marker") {
            const childHtml = this.getTooltipHtmlForToken(tokenId);
            const hexHtml = this.getTooltipHtmlForToken(parentId);
            this.game.addTooltipHtml(attachNode.id, childHtml + hexHtml, this.game.defaultTooltipDelay);
            this.game.addTooltipHtml(parentId, childHtml + hexHtml, this.game.defaultTooltipDelay);
            return true;
        }
        return false;
    }
    /** Return the Level II sibling tokenId iff `tokenId` is a Level I hero/ability card */
    getLevel2Sibling(tokenId) {
        if (getPart(tokenId, 0) !== "card")
            return null;
        const sub = getPart(tokenId, 1);
        if (sub !== "hero" && sub !== "ability")
            return null;
        const last = getIntPart(tokenId, -1);
        if (last % 2 !== 1)
            return null;
        return getParentParts(tokenId) + "_" + (last + 1);
    }
    setupNotifications() {
        console.log("notifications subscriptions setup");
        // automatically listen to the notifications, based on the `notif_xxx` function on this class.
        this.bga.notifications.setupPromiseNotifications({
            minDuration: 1,
            minDurationNoText: 1,
            //logger: console.log, // show notif debug informations on console. Could be console.warn or any custom debug function (default null = no logs)
            //handlers: [this, this.tokens],
            onStart: (notifName, msg, args) => {
                if (msg)
                    this.setActionStatus(msg, args);
            }
        });
    }
    async notif_tokenMoved(args) {
        return super.notif_tokenMoved(args);
    }
    async notif_tokenMovedAsync(args) {
        return super.notif_tokenMovedAsync(args);
    }
    async notif_counter(args) {
        return super.notif_counter(args);
    }
    async notif_counterAsync(args) {
        return super.notif_counterAsync(args);
    }
    async notif_message(args) {
        //console.log("notif", args);
        return gameui.wait(1);
    }
    async notif_log(args, notif) {
        super.notif_log(args, notif);
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
    async notif_endBanner(args) {
        if (args.isWellDestroyed)
            this.bga.gameArea.addLastTurnBanner(args.message);
        else
            this.bga.gameArea.addWinConditionBanner(args.message);
        return gameui.wait(1);
    }
}

export { Game };
