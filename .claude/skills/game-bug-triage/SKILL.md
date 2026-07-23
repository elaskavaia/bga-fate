---
name: game-bug-triage
description: Triage Board Game Arena bug reports for Fate (Defenders of Grimheim) by driving a real browser against boardgamearena.com/bugs, reading each report, cross-referencing the codebase and git history to decide what is already fixed, and updating report status/comments. Use this skill whenever Victoria wants to look at, go through, triage, or respond to BGA bug reports, player reports, or the bug list, mark a report confirmed/fixed/waiting-for-deploy, or check whether a reported bug is already handled - even if she does not say the word "triage" (e.g. "open the bugs page", "look at the second one", "mark that one fixed", "is this bug already deployed?").
argument-hint: optionally "auto" for headless/cron mode (abort if the browser is not authenticated)
---

# Fate BGA Bug Triage

Drive a real browser against the BGA bug tracker, read reports, decide their true state against the code, and update them. This skill assumes the `chrome-devtools-mcp` browser tools are available.

## Scope: what a run covers

A run is two passes. A time window alone is not enough, because the changes that matter most often happen *without the report being touched* - most importantly, when Victoria deploys, a batch of **Waiting for deploy** reports should become **Fixed** even though nothing on them changed.

**1. Status sweeps - always, regardless of when the report last changed.** Filter the list by status and re-evaluate:

- **Waiting for deploy** -> re-check each against what is now deployed. Find its fix commit (`git log --oneline --grep='#<reportid>'`, or the card/op from the `TODO.md` entry) and test whether it has shipped: `git merge-base --is-ancestor <commit> <latest-tag> && echo LIVE`. If the fix is in the latest (deployed) tag, promote the report to **Fixed**. This is exactly how a deploy gets reflected: right after Victoria cuts/deploys a tag, a run flips every now-shipped Waiting-for-deploy report to Fixed.
- **Open** -> always revisit; they are unresolved by definition.

**2. New / changed since the last check - for everything else.** Only look at reports **created or updated since the last check**, tracked by a marker in `misc/docs/TODO.md`:

`**Bug triage last checked:** <timestamp>`

Sort by **Last update**, walk from the top, stop once a row is older than the marker (relative times like "2 hours ago" are enough). This catches freshly-raised reports and real player activity on already-triaged ones (a reply to Info needed, a reopened Fixed report). **Skip reports whose only recent change was our own dev action** - our status flips/comments bump "Last update".

Capture the current time (`date`) at run start; after the run, overwrite the marker with it. This keeps the time-based pass idempotent and resilient to skipped or late runs.

## Running headless (cron)

Headless/cron mode is triggered by the skill argument **`auto`** (e.g. a daily cron invokes `game-bug-triage auto`). In this mode there is no one to do the login handoff, so: take a snapshot first and **if the page shows the login screen or a "Visitor-NNN" user, abort immediately with a clear notice** ("bug triage skipped: browser not authenticated") - never attempt to log in, and don't silently report "nothing to triage". Also be aware the chrome-devtools MCP may not be present at all in a non-interactive run; if the browser tools can't be loaded, abort the same way.

Auth does not survive on its own here: the cron-launched browser must reuse the **same persistent profile that holds Victoria's BGA login** (the chrome-devtools-mcp user-data dir, `~/.cache/chrome-devtools-mcp/`). A run that spins up a fresh/temporary context has no cookie and lands on the login page - which is exactly the abort case above, not something to work around. If `auto` runs keep losing the session, the fix is in the cron's browser/MCP launch config (point it at that profile), not in this skill. A logged-in interactive session and a headless run can also fight over one profile, so don't run both against the same browser at once.

(Without the `auto` argument the skill runs interactively as usual - on a login page it asks Victoria to log in and waits, per the Login section above.)

## The bug list

- Fate's game id is **2758**. The list lives at `https://boardgamearena.com/bugs?game=2758`.
- A single report is `https://boardgamearena.com/bug?id=<REPORT_ID>`.
- Load the tools with `ToolSearch` (`select:mcp__plugin_chrome-devtools-mcp_chrome-devtools__navigate_page,...take_snapshot,...click,...fill`), then `navigate_page`.

### Login

The list requires being logged in as Victoria. If `take_snapshot` shows "Login to Board Game Arena!" or a "Visitor-NNN" user, **stop and ask her to log in** in the MCP-controlled Chrome window, then retry. This session cannot run an OAuth flow - do not attempt to log in yourself or ask for credentials/codes.

## Reading, not looking

Use `take_snapshot`. The snapshot is the page's accessibility tree as **text** - it lets you quote titles, votes, comments, and table ids verbatim, and it gives you the `uid`s needed to click. The bug pages are entirely text, so `take_screenshot` of a report adds nothing; don't bother.

If a reporter attached a screenshot, BGA does not host it - they paste an **external image link** (snipboard.io or similar), which appears in the snapshot as a link URL. To see it, open/fetch that URL; never screenshot the bug page itself.

## What each report tells you

From a `bug?id=` snapshot, pull:

- **Status** header (OPEN / CONFIRMED / WAITING FOR DEPLOY / FIXED / ...) and vote count.
- **Type**: `rules`, `block` (game frozen / softlock), etc.
- **Reported details**: the rule issue, the "replay move" pointer, browser string.
- **Table link** and dev-tools block: "Load on the Studio" (loads the exact dump), table chat log, and the dump's move number - these are your reproduction handles.
- **Comments**: prior reporter/developer notes. A report may already have a dev reply; don't re-answer.

Summarize each report back to Victoria concisely (status, votes, the concrete claim, repro pointer) and connect it to recent work when you can - many reports map directly onto recent commits or cards.

### Non-English reports

Players report in many languages. When the title or details are not in English, **post a comment with a faithful English translation and submit it** - Victoria and other triagers read English, and the translated text becomes part of the report for everyone who looks later. Keep it a plain translation (no added analysis or internals), leave the status unchanged, and still summarize it for Victoria in the chat.

## Deciding the true state

### Rules reports: is the reporter even right?

For a `rules`-type report, the first action is to check the claim against the actual rules, **before** assuming the code is wrong - reporters often misremember a rule. The source of truth, in order:

1. `misc/docs/RULES.md` - the rulebook.
2. If the rule isn't spelled out there, `misc/docs/FORUM.md` - designer clarifications and rulings saved from the forum.

If the rules contradict the reporter, it's likely **Not a bug** (explain the rule in a player-facing comment). If the rules confirm the claim, it's a real report - proceed to whether it's already handled.

### Already fixed? (do this BEFORE any investigation)

**Always run this check first - for every report, including one opened minutes ago.** A fix may already be committed and deployed even for a brand-new report (Victoria often fixes and deploys same-day, and a report can be filed against an old client). Skipping it wastes an investigation and mis-labels a Fixed bug as Confirmed. The very first thing to grep is the **report id itself**, since commit messages reference it (`BGA #<id>`):

- `git log --oneline -i --grep='<report-id>'` - a hit here usually IS the fix. Fall back to `--grep='<card/keyword>'` if the id isn't referenced.
- `git merge-base --is-ancestor <commit> <latest-tag> && echo IN` - is that commit in the latest release tag?
- `git tag --contains <commit> --sort=creatordate | head` - or list the tags that contain it.

If the fix commit is in the latest deployed tag -> the report is **Fixed** right now; do not open an investigation. Only when there is no fix commit do you move on to rules-check / investigation.

**Deployed vs waiting**: a fix living in a release **tag** is not automatically live. Victoria's process is that **creating a tag = deployed**, so if the fix is in the latest tag, it is live -> **Fixed**. If it is only committed/tagged but she has not cut/deployed that tag, it is **Waiting for deploy**. When unsure whether the tag is deployed, ask her rather than guessing.

Locating the exact card/operation and diagnosing an unshipped bug is **not triage work** - hand that to the investigation agent below.

## When a report lacks detail, ask - don't dig

Most reports are thin: no replay/move pointer, no repro steps, no clear statement of what was expected. The right response is almost always to **ask the reporter for what's missing** (set status **Info needed**, request the specific detail - move number, what they expected vs saw, whether F5 helped), not to launch an investigation. Deep root-cause hunting is the exception, reserved for a report that is concrete and reproducible but whose cause still isn't obvious.

## Deep investigation via a background agent

When a report does warrant it, spin off a background `general-purpose` agent to find and **prove** the cause. Its boundary: it may **write and run tests** (an integration test under `tests/Campaign/`, or a targeted unit test) to try to reproduce the bug, but it must **not edit any production code** - no `modules/php` source, no CSVs, no `.ts`/`.scss`. It has no browser. It reports back one of:

- **CONFIRMED** - it wrote a test that actually reproduces the bug (a red test demonstrating the broken behavior), and reports the test name plus file:line root cause and a proposed fix in prose. This is the only outcome that earns a **Confirmed** status on the report. **The agent must LEAVE that reproducing test in the tree - never delete it.** It is the exact red test the fix will be verified against (it goes green when fixed); throwing it away destroys the most valuable thing the investigation produced. Tell the agent this explicitly in its prompt, and have it report the test's file path and method name. (A red test in the suite is expected for a known-unfixed bug; note it to Victoria so `predeploy` failing there is no surprise.)
- **UNCONFIRMED** - a plausible hypothesis from reading code, but no test reproduces it yet. It says what it tried and why it couldn't repro. Treat this as a lead, not a fact.
- **NOT FOUND** - no lead; plus a short list of specific questions to ask the reporter (move number, what they expected vs saw, which card/hero/monster, did F5 help). This is what makes a dead end useful - you turn those questions into an **Info needed** comment.

**A code-reading hypothesis is not a confirmation.** Finding a code path that _could_ produce the symptom is easy to get wrong - it happened here: a confident "cause" for #233418 was later shown to have no reproducible case. Only a test that actually reproduces the bug proves it. So the agent's job is to _try to write that test_, not just narrate a trace.

You keep the browser and any `TODO.md` edits, because the MCP is a single shared Chrome session and a second agent navigating in parallel would clobber your tab. Keep triaging other reports while it runs.

**Time-box it.** The read-only hypothesis pass should be quick (~a minute); if the agent then attempts a reproducing test, hold it to **one focused attempt** - if that test doesn't repro, it returns UNCONFIRMED rather than grinding through variations. There is no wall-clock kill switch on the agent, so this budget has to live in its prompt.

## Updating a report

The status control is a button showing the current status; clicking it reveals the options. What each one means and when to reach for it:

- **Open** - default / untriaged, or a real report still without a reproducing test. Leave it here while investigating.
- **Info needed** - the report is too thin to act on; you've asked the reporter for the specific missing detail. The landing spot for the "ask, don't dig" flow and for an agent's NOT FOUND questions.
- **Confirmed** - reproduced by a test (see the rule below). Real bug, cause understood, fix pending.
- **Waiting for deploy** - fixed in code but not yet live (fix committed/tagged but that tag isn't deployed).
- **Fixed** - fix is deployed and live (in the latest deployed tag). Git-verified, no re-test needed.
- **Duplicate** - the same issue as another report already on the list. Use it when a new report restates a bug you're already tracking; in the comment, point the reporter to the canonical report number so votes/discussion consolidate there. Prefer keeping the older / more-detailed / higher-voted one as canonical. (Example this project has seen: a second "Bjorn attack range" report was closed Duplicate against the original.)
- **Won't fix** - real behavior but a deliberate decision not to change it (out of scope, by design, not worth the cost). Victoria's call, not yours - don't set it on your own.
- **Works for me** - couldn't reproduce and it looks environmental or transient, but you can't positively say the rules make it correct. Weaker than Not a bug.
- **Not a bug** - the behavior is correct per the rules (`RULES.md` / `FORUM.md`); the reporter misunderstood. Explain the rule in a player-facing comment.

If no news leave the status unchanged (post a comment without moving the report). Use when you're only replying. _(If unsure it behaves this way, verify by reload after posting.)_

**Feature requests are not bugs - reclassify.** If a report asks for a *new feature or capability* rather than reporting broken behavior ("add a token display mode", "let me sort my hand"), it doesn't belong in the bug list. Use the **Reclassify as suggestion** link on the report so players can vote on it as a suggestion. If one report mixes a real bug with a feature ask, handle the bug and, in a player-facing comment, ask the reporter to raise the feature separately as its own suggestion so it can gather votes on its own.

**Don't confirm without a reproducing test.** Set a report to **Confirmed** only when an integration test actually reproduces the bug. A plausible code trace is a hypothesis, not a confirmation - record it in `TODO.md` as a lead and leave the report **Open** (or **Info needed** if it's thin), but do not flip it to Confirmed until a red test demonstrates the failure. (Deployed fixes still go straight to **Fixed** - that path is verified by git, not by re-testing.)

To change status:

1. Click the status dropdown, then click the target option. BGA pre-fills the comment box with a template for that status (e.g. Confirmed -> "I was able to reproduce the issue and will handle it as soon as possible.", Fixed -> "The bug has been fixed. Thanks for reporting!").
2. **The change is staged, not saved.** Commit it by clicking **Post comment**. The status header still reads the old value until you post. **Re-`take_snapshot` before this click** - selecting the option re-renders the page, so `uid`s from the pre-selection snapshot are stale and a stale `uid` can land the click on the wrong element (e.g. an image link in a comment), navigating you off the page instead of posting.
3. `navigate_page` reload and `take_snapshot` to **verify** - confirm the header changed and the comment landed. The submit is async, so a stale pre-reload snapshot can look like it failed when it didn't.

### Comment discipline

**Never put technical details (root cause, file names, card ids, code) in a public bug comment.** Reports are player-facing. A comment should say only what a player needs: that it's confirmed, or fixed, or what info is missing. The BGA status template already gets this right - prefer it over writing your own. Keep all root-cause/fix detail internal (see below).

Post a comment without a status change only when Victoria asks you to reply to a reporter (e.g. "tell them point 2 is fixed pending deploy"). Same discipline: user-facing wording, no internals. On a multi-claim report, keep the status OPEN if any claim is still unresolved.

## Recording the fix internally

Record findings in `misc/docs/TODO.md` (this is where internals live, not the bug report). Add a `[ ]` entry to the running bug list at the top of the file, in the existing style: reference the BGA report id and the card/op id, and give the concrete fix plus a test note. Match surrounding formatting; don't disturb other entries.

Be honest about certainty. A **CONFIRMED** finding (backed by a reproducing test) can state the cause plainly and name the test. An **UNCONFIRMED** lead must be written as a hypothesis - say "suspected cause / no reproducible case yet", not a flat assertion - so a future reader doesn't act on an unproven trace as if it were fact.

## Guardrails

- Changing a report's status and posting a comment are **outward-facing, in Victoria's name**. Do them when she has asked (directly or via the triage flow she set up); when in doubt about whether to post, confirm first.
- Do not use the `gh` CLI or any GitHub tooling - triage is git + browser only.
- Report outcomes faithfully: after each update, tell her exactly what status it now shows and what the public comment says.
