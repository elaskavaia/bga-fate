# Plan for Implementation of game Fate: Defenders of Grimheim on BGA
This document is a plan for the implementation of the game Fate: Defenders of Grimheim on Board Game Arena (BGA). It outlines the steps and tasks required to create a digital version of the game that can be played online.

## Prepare game assets
[x] Read the rulebook of Fate: Defenders of Grimheim and create RULES.md.
[x] Assets of the game including rulebook PDF located at ~/Develop/bga/bga-assets/
[ ] Main Board (jpg) at least 2048px width
[ ] Player boards (jpg) one per hero
[ ] Cards (jpg) at least 125px width - sprite - one per hero plus monster cards one sprite for all
[ ] Miniatures (png) - sprite
[ ] Other 3d pieces and iconography (png) - sprite


## High level plan
[x] Transform templated project into typescript enabled
[ ] Copy boiletplate code from another game: tokens db, machine db, common utils, etc
[ ] Implement general rules
[ ] Implement rules for one specific monster type
[ ] Implement rules for one specific hero type
[ ] Iterator to add all other monster types and legends
[ ] Iterator to add all other heros

