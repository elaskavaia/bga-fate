---
name: deploy
description: Build and prepare for BGA deployment
disable-model-invocation: true
---

Prepare the Wayfarers game for BGA deployment:

#. Run the full build process: `npm run build`
#. Check for any build errors in TypeScript, SCSS, or material generation
#. Run tests: `npm run tests`
#. Check and fix failed tests
#. Show git status to see which files have changed
#. Check for spelling mistakes and issues in changed code
#. Check to see if new php tests should be added

Do not create a git commit unless explicitly asked.
