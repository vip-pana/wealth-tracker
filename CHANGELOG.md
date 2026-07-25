# Changelog

All notable changes to Wealth Tracker are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0](https://github.com/vip-pana/wealth-tracker/compare/v1.1.0...v1.2.0) (2026-07-25)


### Features

* add snapshot/month toggle to the dashboard ([1b7289c](https://github.com/vip-pana/wealth-tracker/commit/1b7289cd9eb32073b38215dcf21751775da7b853))
* advisor chat UX — rotating insights, typewriter titles, scroll & new-chat polish ([486449c](https://github.com/vip-pana/wealth-tracker/commit/486449cfbdd01a23a3e6c82af4bdc95133caa77a))
* advisor objective/allocation default to the Goal section (override in profile) ([0048ee8](https://github.com/vip-pana/wealth-tracker/commit/0048ee87a66c776127a169a694ae730e82b3eea1))
* advisor opens on a new conversation, animate only page entry ([cbd954a](https://github.com/vip-pana/wealth-tracker/commit/cbd954ae6c0ceaa9bf7197de809903b3e286450a))
* **advisor:** add a Riprova button to failed chat replies ([1eba37d](https://github.com/vip-pana/wealth-tracker/commit/1eba37d733ecf64e3a2f0e3f721eff7a30efc3f1))
* **advisor:** add Regolo (EU, ZDR) as a third advisor driver ([49cf95d](https://github.com/vip-pana/wealth-tracker/commit/49cf95d2a3aa7f30cb55227baf93c96525ec64e0))
* **advisor:** add three more widget-emitting tools ([47a1940](https://github.com/vip-pana/wealth-tracker/commit/47a1940638ce9c4ae9b7f605d5a5b0360405cd63))
* **advisor:** add widget collector + widgets column for generative UI ([57c4b7c](https://github.com/vip-pana/wealth-tracker/commit/57c4b7c5e4540286c357d8b12053c90b2fd7ef42))
* **advisor:** auto-grow the chat input up to 15 rows ([3d37fb5](https://github.com/vip-pana/wealth-tracker/commit/3d37fb54f3afe5945eeb050e047a5071e9e7053e))
* **advisor:** current target = next milestone's allocation — phase 2 ([27a1a44](https://github.com/vip-pana/wealth-tracker/commit/27a1a441119c8df66733bf31950563c4fa8daefa))
* **advisor:** discuss and propose the goal via confirm cards ([058d5ee](https://github.com/vip-pana/wealth-tracker/commit/058d5ee7b4c742a80f8ff22044737b416bd6a65a))
* **advisor:** emit UI widgets from the advisor tools ([99c2627](https://github.com/vip-pana/wealth-tracker/commit/99c2627e848547b4e0f77f5fe0c01f17070b48d5))
* **advisor:** give the advisor full emergency-fund coverage ([a9aa00c](https://github.com/vip-pana/wealth-tracker/commit/a9aa00c222375f84eec2b654efb00bd02ad3e297))
* **advisor:** give the AI advisor callable tools via Prism ([5d6b3c6](https://github.com/vip-pana/wealth-tracker/commit/5d6b3c63750a0763671674c660d9ebd40f5996a7))
* **advisor:** let the AI propose an investor-profile update ([357f714](https://github.com/vip-pana/wealth-tracker/commit/357f714997d4fd211babb1eb9a478c3bce5f3959))
* **advisor:** let the AI run a risk-profiling interview ([6194811](https://github.com/vip-pana/wealth-tracker/commit/61948116666a9e55dc501dc5ea69415aa1222375))
* **advisor:** let the AI update its memory autonomously via remember_fact ([5ef8097](https://github.com/vip-pana/wealth-tracker/commit/5ef809722507e8ef5b81aebbc21cd1358669e315))
* **advisor:** let the interview ask age as human context, not a field ([4ee8251](https://github.com/vip-pana/wealth-tracker/commit/4ee8251b2051ca0e747194c81d059e0c93ed37a2))
* **advisor:** model a growing PAC and show its yearly schedule ([de81570](https://github.com/vip-pana/wealth-tracker/commit/de81570187d572d7ad6e2b00dc900b511969ddba))
* **advisor:** offer a button to generate the proposal instead of asking in words ([14b539f](https://github.com/vip-pana/wealth-tracker/commit/14b539f6dca9959b3d2152a4d0abec6c46cf3301))
* **advisor:** per-milestone target allocation — phase 1 (model + advisor) ([3722205](https://github.com/vip-pana/wealth-tracker/commit/372220574b14a9b28788d7af2bd99b60ad0bfe60))
* **advisor:** personal profile fields (name, birth date/age) and memory ([df41f04](https://github.com/vip-pana/wealth-tracker/commit/df41f047520fb041c42d608706330edb0ef46ce9))
* AdvisorProvider contract + Ollama local provider (layer 2, step 1) ([a7eaa9d](https://github.com/vip-pana/wealth-tracker/commit/a7eaa9d0b481d51722378632aa9d73f958ee82bf))
* **advisor:** render generative UI widgets in the chat ([9ea8b1d](https://github.com/vip-pana/wealth-tracker/commit/9ea8b1d7aee3c8ef9aa57a5aadde42f0dff64715))
* **advisor:** render tabular data as widgets, not ASCII tables ([3215dda](https://github.com/vip-pana/wealth-tracker/commit/3215dda31d214a85ae7766b7b5abb66c110248ad))
* **advisor:** render the profile-proposal confirmation card ([ea449fe](https://github.com/vip-pana/wealth-tracker/commit/ea449fed6706fcc0cd33570a5683a042106d4590))
* **advisor:** render the three new widgets in the chat ([18b0c8a](https://github.com/vip-pana/wealth-tracker/commit/18b0c8a6bc5d9cdf6ab3df2eec1afd6788153515))
* **advisor:** require a real interview before proposing a profile ([8e34192](https://github.com/vip-pana/wealth-tracker/commit/8e34192df8d910893ec090b09118bcaafbd8c2d8))
* **advisor:** require four user turns before proposing a profile ([ec1f248](https://github.com/vip-pana/wealth-tracker/commit/ec1f248de60b0b955a6f26f61ec9caffa5d8dcd5))
* **advisor:** retry a failed chat reply in place ([7e962d0](https://github.com/vip-pana/wealth-tracker/commit/7e962d01f4072bcf46d7c48e371ebba565402685))
* **advisor:** show chat date next to the session title ([09a6f1e](https://github.com/vip-pana/wealth-tracker/commit/09a6f1e839d1b24bc53cfa022937b47eb54bfc07))
* **advisor:** show which tool the AI is using during a chat reply ([c732651](https://github.com/vip-pana/wealth-tracker/commit/c732651073a6c3472007b4dc072cc8744529a22e))
* **advisor:** surface profile notes and add interview triggers ([9cf24b3](https://github.com/vip-pana/wealth-tracker/commit/9cf24b3efff21053104996f8065791a26e02920b))
* **advisor:** tune the local model call (keep_alive, temperature, num_ctx) ([cbfcbb8](https://github.com/vip-pana/wealth-tracker/commit/cbfcbb8888045003ede921439163a9bf8e9e5558))
* **advisor:** update profile facts directly from chat via a confirm card ([8b3a825](https://github.com/vip-pana/wealth-tracker/commit/8b3a825edcedab49e23c01910d05b37ca6c2b558))
* AI Advisor section with on-demand report (layer 2, step 3) ([681b2e8](https://github.com/vip-pana/wealth-tracker/commit/681b2e8a9c5d5cad70069b519948d3c2c7653d6e))
* artisan command to import Scalable transactions (step 4b trigger) ([18954bb](https://github.com/vip-pana/wealth-tracker/commit/18954bb0f34286400e73ad0099144295addad0c9))
* **banking:** ingest bank-account transactions from Enable Banking ([fb34595](https://github.com/vip-pana/wealth-tracker/commit/fb34595a3c663d374e063c34b8cc6f7a77f95c3d))
* build advisor context + generate report action (layer 2, step 2) ([62b4fad](https://github.com/vip-pana/wealth-tracker/commit/62b4fad9f2f4c04e19b0d9295c6013a9c54f32bb))
* capture PAC/single transaction source from Scalable, show in history ([1d2d21c](https://github.com/vip-pana/wealth-tracker/commit/1d2d21c49dc1ff90b2c158ce310cba2b78dbe5b7))
* **cashflow:** add Entrate e Uscite page with batch editing and monthly cards ([efe304a](https://github.com/vip-pana/wealth-tracker/commit/efe304ae928340883a166112ede75ad4b705ebca))
* **cashflow:** auto-classify bank transactions as income/expense/transfer ([4e88061](https://github.com/vip-pana/wealth-tracker/commit/4e880611f79e7fff569aebcb501641816a3f6916))
* **cashflow:** emergency-fund target with months-covered coverage ([3c50dc9](https://github.com/vip-pana/wealth-tracker/commit/3c50dc9e1970c16e4c9e40e9ff8d2824a7776923))
* compute real fun facts about the user's data for the advisor ([7d9890c](https://github.com/vip-pana/wealth-tracker/commit/7d9890c3bcb1abd5bf1a8d18d534e42f7c5bd493))
* ComputePosition — derive shares, avg cost and P&L from transactions (step 2) ([f6f4209](https://github.com/vip-pana/wealth-tracker/commit/f6f4209603854016f3392366e5e1c4e83c027dc3))
* conversational advisor sessions — chat + report, persisted history ([a59be17](https://github.com/vip-pana/wealth-tracker/commit/a59be175aba67cae9493fa7b32e129e1d5089010))
* enrich advisor context with costs (TER) and derived PAC contribution ([f6cf9c8](https://github.com/vip-pana/wealth-tracker/commit/f6cf9c820fe00fd4826956f3f4baca169b6409ee))
* generate advisor chat replies in the background ([4116b6b](https://github.com/vip-pana/wealth-tracker/commit/4116b6b75b38235a68b4e5e89e3c428bfba84dfc))
* generate the advisor report in the background (queue + poll + toast) ([2960bb4](https://github.com/vip-pana/wealth-tracker/commit/2960bb4d5afa19eb44c17e2a638d8b0445bb5c1d))
* **goal:** add an 'AI redefine' entry point in the goal edit dialog ([018f4be](https://github.com/vip-pana/wealth-tracker/commit/018f4be3a6a22f2d091f4460516a6b80f636a88a))
* **goal:** per-category absolute cap on milestone allocations ([d382bc5](https://github.com/vip-pana/wealth-tracker/commit/d382bc50647e390b2c407bcd4c14f666bf4e0138))
* **goal:** per-milestone target allocation in the manual form — phase 3 ([326ec9d](https://github.com/vip-pana/wealth-tracker/commit/326ec9d2be0ce8a39a2e59f21e5e2c1532cdb7a4))
* **goal:** per-milestone target allocation with a glide-path bar ([3a14092](https://github.com/vip-pana/wealth-tracker/commit/3a1409249860b1818a264af346080401f07a64fe))
* **goal:** show milestone allocation in the accordion, single-column layout ([5038458](https://github.com/vip-pana/wealth-tracker/commit/503845885341f1811515e7d56097120b00155b8d))
* hide chart values in privacy mode (tooltip off + masked Y axis) ([e396326](https://github.com/vip-pana/wealth-tracker/commit/e3963261c89a0480c0c2594a797115be89bb1d34))
* import Scalable transaction history into transactions (step 4b) ([b247b3b](https://github.com/vip-pana/wealth-tracker/commit/b247b3b14bd53fd1d92f79e5e78c20e24670c01f))
* **input:** reconcile snapshot net worth against the current month's rows ([14027c4](https://github.com/vip-pana/wealth-tracker/commit/14027c4cce0717977f17f576211434d83981f72b))
* investor profile feeds the advisor (layer 2, step 4) ([55931f0](https://github.com/vip-pana/wealth-tracker/commit/55931f08be92422df2bc681b55b47de14ea4a239))
* let the user cancel an in-flight Scalable CLI login ([6e82b4c](https://github.com/vip-pana/wealth-tracker/commit/6e82b4c74e126fd4d24a6a6869b0669f8fba76b9))
* persist the advisor report in localStorage across refreshes ([317ac06](https://github.com/vip-pana/wealth-tracker/commit/317ac0618de039bb40bcde2fe3601965561b20bd))
* persisted in-app notification system with bell + unread feed ([1c2dcfd](https://github.com/vip-pana/wealth-tracker/commit/1c2dcfdda4348cb3ca592e2de5fd4879e9102955))
* portfolio metrics engine + dashboard insights card ([40ca142](https://github.com/vip-pana/wealth-tracker/commit/40ca142032f52e2cdb8c97198693c9ad4574cacc))
* **portfolio:** non-investable emergency-fund buffer, excluded from investment metrics ([77610fc](https://github.com/vip-pana/wealth-tracker/commit/77610fca187bccecc45a9df67c0388eeb2ea27a4))
* privacy toggle to hide monetary values ([6319b58](https://github.com/vip-pana/wealth-tracker/commit/6319b58ed9e3be2bb103b8efefa2b669f10d807a))
* rename advisor sessions inline ([d6a8b90](https://github.com/vip-pana/wealth-tracker/commit/d6a8b90bf4ef3b1bb2ec8b6e94de3031119370e9))
* replace the unused Analisi section with an Investimenti section ([1335fb6](https://github.com/vip-pana/wealth-tracker/commit/1335fb6cd43cf5ac081ba72a69bbeb8e4b505325))
* **scalable:** keep the CLI session alive with a 6-hourly ping ([4cf8e81](https://github.com/vip-pana/wealth-tracker/commit/4cf8e81f05b6208cd0fe976b33393e6597229155))
* show milestone checkpoints on the goal progress bars ([ad4ac47](https://github.com/vip-pana/wealth-tracker/commit/ad4ac47a2a6fca403503082ec3f1f31a3f2170f0))
* **snapshots:** daily automatic snapshot, only when every source is fresh ([6ad0bb7](https://github.com/vip-pana/wealth-tracker/commit/6ad0bb7a7c1de5445b4dfe5a22129e30e0a3c2b8))
* stream advisor chat replies token-by-token ([20c7ced](https://github.com/vip-pana/wealth-tracker/commit/20c7ced3e0e5a9431561031f14f9a7902b220690))
* stream chat UI + skip refresh on active session click ([a4687c2](https://github.com/vip-pana/wealth-tracker/commit/a4687c221ef018db24f2941a89effd8ee227ecb2))
* transaction-managed assets sync quantity and lock manual edits (step 3) ([b7d1f5c](https://github.com/vip-pana/wealth-tracker/commit/b7d1f5cb3f34531a1615431cb009b5acb14bc3c8))
* transactions table, model and factory (step 1 of share-level positions) ([05b18f0](https://github.com/vip-pana/wealth-tracker/commit/05b18f0dfdedc80014da733e68ef045c83654bb5))
* true per-position return from transactions (step 5) ([274be73](https://github.com/vip-pana/wealth-tracker/commit/274be7395bd9400d5d2b10b35bb9ae08cc16eb59))
* two-column goal edit dialog + shared OptionalHint ([d85f6a3](https://github.com/vip-pana/wealth-tracker/commit/d85f6a3d1ff652c21913391dc749d5a7d1cb7a85))
* unlink an asset from its transactions in Settings ([a42d21f](https://github.com/vip-pana/wealth-tracker/commit/a42d21face44cf3ad943681c0bb7cef4a833258a))
* view-only transactions dialog per asset (step 4) ([a417cfa](https://github.com/vip-pana/wealth-tracker/commit/a417cfaa4d10fbe7cde0f63effacb2949ebfd1cf))
* warn on a likely-typo value at asset entry ([14eb997](https://github.com/vip-pana/wealth-tracker/commit/14eb997b777e0264626f8cc53f07b8eb53a2d7e8))


### Bug Fixes

* **a11y:** add DialogDescription to every dialog ([18394b7](https://github.com/vip-pana/wealth-tracker/commit/18394b77b68771ed0dd63af5769dfb87bd1f7a15))
* advisor completion toast now fires reliably on pending→done ([abbe0b9](https://github.com/vip-pana/wealth-tracker/commit/abbe0b905e13f57b99380e8798fd6d02ee720aa4))
* advisor toast — drive it off the status state, not the poll closure ([cf749a1](https://github.com/vip-pana/wealth-tracker/commit/cf749a145ffedefbb16b7887e372c3dd41f16499))
* **advisor:** 5 goal-interview defects from session 112 ([4284d70](https://github.com/vip-pana/wealth-tracker/commit/4284d707f50adda466f3397481b28ac614f5da39))
* **advisor:** answer the user's questions before continuing the interview ([920792e](https://github.com/vip-pana/wealth-tracker/commit/920792ea89c3331b04b1c0da259a73e249002483))
* **advisor:** bind a configured provider in ProposeControllerTest ([4970c11](https://github.com/vip-pana/wealth-tracker/commit/4970c11d333397b81b4e49ac4d074aa698b14c82))
* **advisor:** deepen the risk interview and stop compulsive proposing ([089c41b](https://github.com/vip-pana/wealth-tracker/commit/089c41b24710177b65b0f52594e0a69022574322))
* **advisor:** don't ask to update the profile early in the interview ([f8bc759](https://github.com/vip-pana/wealth-tracker/commit/f8bc75952e271a9a60c079f844d9179595ad7061))
* **advisor:** don't re-offer an already-applied profile proposal ([d28286d](https://github.com/vip-pana/wealth-tracker/commit/d28286d8813acd67897453a8ad3672a5b9ff391b))
* **advisor:** expose the goal's milestones and description in the context ([0770c9f](https://github.com/vip-pana/wealth-tracker/commit/0770c9fcd05e16a8811b15770a1556581327a585))
* **advisor:** gate profile proposals behind explicit user consent ([d21c72a](https://github.com/vip-pana/wealth-tracker/commit/d21c72aee68d1a11a0fda2c95044cef5d62a3155))
* **advisor:** goal simulator uses the backend month count so it matches the prose ([c619001](https://github.com/vip-pana/wealth-tracker/commit/c619001eaf82ef1e262bc67f83e5e34b31c8b976))
* **advisor:** harden tool use — no recited calls, cover non-tx positions ([92e4b79](https://github.com/vip-pana/wealth-tracker/commit/92e4b79fcd602cef485b1c9f7fa36dcbe61dc116))
* **advisor:** hide the 'nothing saved until Applica' hint after applying ([c4b5e6b](https://github.com/vip-pana/wealth-tracker/commit/c4b5e6b5f7b3f9900b798b91928959cdbccfcd42))
* **advisor:** keep only the last profile proposal per reply ([f206fee](https://github.com/vip-pana/wealth-tracker/commit/f206feeb775858c3433d0e66455f00fe7fa6f574))
* **advisor:** make goal/profile interview flow reliable and button-only ([3ab5f11](https://github.com/vip-pana/wealth-tracker/commit/3ab5f113a5e24182bddc7b6bf17362932fc11307))
* **advisor:** make the chat usable on phones and tablets ([259aad7](https://github.com/vip-pana/wealth-tracker/commit/259aad70a0c68283af89dea0478ee77cc5158b9b))
* **advisor:** make the Goal the single source of objective + target allocation ([c649e4f](https://github.com/vip-pana/wealth-tracker/commit/c649e4f29aa8483699880d8ca1cae048b8a02788))
* **advisor:** make the model quote the simulator figures exactly ([4939826](https://github.com/vip-pana/wealth-tracker/commit/4939826b0273226bf316fdc422586c9366bfd2a2))
* **advisor:** make the proposal button track the live conversation ([31ff946](https://github.com/vip-pana/wealth-tracker/commit/31ff9465e8cf84861b006025e4d53b1eda8c92f4))
* **advisor:** make the risk interview multi-turn and deeper ([3e174e6](https://github.com/vip-pana/wealth-tracker/commit/3e174e6c89e191c438f95601b7c689598d6816c9))
* **advisor:** make the risk-profiling interview actually ask first ([9175f3e](https://github.com/vip-pana/wealth-tracker/commit/9175f3e4e4769fc5fa1ac5c916f0ccd4aefdeed6))
* **advisor:** move the queue to its own SQLite file to stop DB locks ([16b2d7b](https://github.com/vip-pana/wealth-tracker/commit/16b2d7bd51f7ffe191badc85f6dac85d69e1c5e5))
* **advisor:** nudge the model to call the propose tool once consent is given ([4de5f45](https://github.com/vip-pana/wealth-tracker/commit/4de5f45a509721456ac6621dad25af681ba8eab6))
* **advisor:** re-sync the profile dialog when the profile prop changes ([ea158e4](https://github.com/vip-pana/wealth-tracker/commit/ea158e4fe34b1610cc9130124fb0c48edf935faa))
* **advisor:** reopening a session must exit "new conversation" mode ([e9d17cc](https://github.com/vip-pana/wealth-tracker/commit/e9d17ccbd4aec7629ec7ad5335c661676d000018))
* **advisor:** retry a failed proposal turn by re-running the proposal ([0a9beeb](https://github.com/vip-pana/wealth-tracker/commit/0a9beebd7fee26159f1487afe338ec44539b3adc))
* **advisor:** retry empty replies and lower Regolo temperature ([ae74fe3](https://github.com/vip-pana/wealth-tracker/commit/ae74fe37e788955e24d0581f2ba7a44d7c083104))
* **advisor:** show the proposal button as a deterministic fallback ([0818339](https://github.com/vip-pana/wealth-tracker/commit/081833963bdb4c55b5d21b64f6a033e1a0b0734a))
* **advisor:** targeted proposals + richer milestones + implicit allocation intent ([1c769ac](https://github.com/vip-pana/wealth-tracker/commit/1c769ac3243fb744017d1b08416b09cf441978e3))
* **advisor:** the AI is the advisor — never punt to "a financial advisor" ([08889e1](https://github.com/vip-pana/wealth-tracker/commit/08889e168e94fc2165510a7cb01a9df287811c5a))
* **advisor:** the risk interview reads the data before asking ([a9118e8](https://github.com/vip-pana/wealth-tracker/commit/a9118e8658c713b2a989bc00936f5faae6dfcdfd))
* **advisor:** tolerate a DB without the snapshots table when building tools ([7a59344](https://github.com/vip-pana/wealth-tracker/commit/7a59344945a620ee3702b311f6183e61728b5516))
* **advisor:** tolerate milestone widgets saved before per-milestone allocation ([06e9c23](https://github.com/vip-pana/wealth-tracker/commit/06e9c2347556964d2ddc14cf9b1d691963533169))
* annotate advisor context + tighten the ethical boundary (layer 2) ([9eed8a7](https://github.com/vip-pana/wealth-tracker/commit/9eed8a7c2aaf89941dc928911b1439016fac77e2))
* enable SQLite WAL + busy_timeout to stop "database is locked" ([0401d56](https://github.com/vip-pana/wealth-tracker/commit/0401d56e2b6135bf0050e25e372852075e76ac35))
* **goal:** show and preserve milestone action/rationale in the manual form ([93c853b](https://github.com/vip-pana/wealth-tracker/commit/93c853b432743d75a2b28dc26c1cc55aa382ccab))
* lock the quantity field for transaction-managed assets in the form ([b98c2c0](https://github.com/vip-pana/wealth-tracker/commit/b98c2c0afc01cf167c04e8df7319e2c7b1affc01))
* make the queue worker resilient (auto-restart) so jobs never stall ([102c2db](https://github.com/vip-pana/wealth-tracker/commit/102c2db51ee661310edf1fadd5637fa084e9fc27))
* mask amounts passed as string props (dashboard summary, insights, tx dialog) ([6e510c0](https://github.com/vip-pana/wealth-tracker/commit/6e510c0ce36802ba9590820e100942da4abc0142))
* mask the allocation donut legend values and tooltip in privacy mode ([a819975](https://github.com/vip-pana/wealth-tracker/commit/a8199750f1622cd22c5326804a1ca5cf19cd1665))
* **notifications:** dismiss advisor notification when its session is opened ([b04fc27](https://github.com/vip-pana/wealth-tracker/commit/b04fc27397eb2fbc5b3d2c97fd8bed7f0cf75e06))
* **notifications:** resolve the Scalable sync-failed warning on reconnect ([5a6d736](https://github.com/vip-pana/wealth-tracker/commit/5a6d73631abdaf1bcbc936698c12e3422ef29013))
* **prices:** backfill ETF TER from the .MI symbol, not just the bare ticker ([020d84f](https://github.com/vip-pana/wealth-tracker/commit/020d84f315ec42eda59c35bffd0c99ca910fd96a))
* **queue:** stop advisor replies from failing under a slow local model ([58c017f](https://github.com/vip-pana/wealth-tracker/commit/58c017f7640e2476cc73405acb750ab788f7bf04))
* raise queue:listen timeout above the Scalable login job's 900s ([a0e3ea0](https://github.com/vip-pana/wealth-tracker/commit/a0e3ea0a68b0bf37ec282121dde7e262e79b84ce))
* render all markdown heading levels (#### was shown as literal text) ([3d9d6ac](https://github.com/vip-pana/wealth-tracker/commit/3d9d6ac74c731fbd91972855aa9b8a27326bffc9))
* show the sent question optimistically when starting a new chat ([4eff197](https://github.com/vip-pana/wealth-tracker/commit/4eff19773b6770dc0b86debaa821aa566fa330a5))
* **ui:** do not re-navigate when clicking the already-active sidebar link ([ae5e41a](https://github.com/vip-pana/wealth-tracker/commit/ae5e41ad05c1b01d0b55c4acd9438270d0ab232d))
* **ui:** scrollable session list and suggestion chips, tighter chart legend ([03dd13a](https://github.com/vip-pana/wealth-tracker/commit/03dd13a35e359a2689c65b20efc77f1f6c246148))
* use axios for the generate POST so CSRF passes (was 419) ([5024f1e](https://github.com/vip-pana/wealth-tracker/commit/5024f1e943c62cc439ff13c048e5838d7e7c30f1))


### Refactoring

* **advisor:** drop qualitative emergency_fund, use the real tagged buffer ([8eb38e5](https://github.com/vip-pana/wealth-tracker/commit/8eb38e5c05ac6d9c0274c32f36e3a2cd60e9a4ba))
* **advisor:** use observed net income, drop hand-entered income_monthly ([a8cac27](https://github.com/vip-pana/wealth-tracker/commit/a8cac27844124907a91b014b690aff1bde79d402))
* extract stepMonth month arithmetic into a lib helper ([8b8a34f](https://github.com/vip-pana/wealth-tracker/commit/8b8a34fc07d75fa8483c5b6928b66252e1aed912))
* **goal:** give the milestone label an explicit heading ([0d8277e](https://github.com/vip-pana/wealth-tracker/commit/0d8277e6aedf249ff2fc2f4b09bf4251de0af51e))
* remove the unused category emoji (icon) field ([1fb5712](https://github.com/vip-pana/wealth-tracker/commit/1fb5712f1e5b881b5de2a890b4857d6f5b093383))
* split large advisor/settings/goal/pension pages into components ([b7946a4](https://github.com/vip-pana/wealth-tracker/commit/b7946a487b4f6c985d3da6785f997d2af145f2dc))

## [1.1.0](https://github.com/vip-pana/wealth-tracker/compare/v1.0.0...v1.1.0) (2026-07-25)


### Features

* add an optional ISIN to assets, editable in the asset form ([6cb266e](https://github.com/vip-pana/wealth-tracker/commit/6cb266e569dacfaf624155c873bb0b7df023f738))
* add snapshot/month toggle to the dashboard ([1b7289c](https://github.com/vip-pana/wealth-tracker/commit/1b7289cd9eb32073b38215dcf21751775da7b853))
* advisor chat UX — rotating insights, typewriter titles, scroll & new-chat polish ([486449c](https://github.com/vip-pana/wealth-tracker/commit/486449cfbdd01a23a3e6c82af4bdc95133caa77a))
* advisor objective/allocation default to the Goal section (override in profile) ([0048ee8](https://github.com/vip-pana/wealth-tracker/commit/0048ee87a66c776127a169a694ae730e82b3eea1))
* advisor opens on a new conversation, animate only page entry ([cbd954a](https://github.com/vip-pana/wealth-tracker/commit/cbd954ae6c0ceaa9bf7197de809903b3e286450a))
* **advisor:** add a Riprova button to failed chat replies ([1eba37d](https://github.com/vip-pana/wealth-tracker/commit/1eba37d733ecf64e3a2f0e3f721eff7a30efc3f1))
* **advisor:** add Regolo (EU, ZDR) as a third advisor driver ([49cf95d](https://github.com/vip-pana/wealth-tracker/commit/49cf95d2a3aa7f30cb55227baf93c96525ec64e0))
* **advisor:** add three more widget-emitting tools ([47a1940](https://github.com/vip-pana/wealth-tracker/commit/47a1940638ce9c4ae9b7f605d5a5b0360405cd63))
* **advisor:** add widget collector + widgets column for generative UI ([57c4b7c](https://github.com/vip-pana/wealth-tracker/commit/57c4b7c5e4540286c357d8b12053c90b2fd7ef42))
* **advisor:** auto-grow the chat input up to 15 rows ([3d37fb5](https://github.com/vip-pana/wealth-tracker/commit/3d37fb54f3afe5945eeb050e047a5071e9e7053e))
* **advisor:** current target = next milestone's allocation — phase 2 ([27a1a44](https://github.com/vip-pana/wealth-tracker/commit/27a1a441119c8df66733bf31950563c4fa8daefa))
* **advisor:** discuss and propose the goal via confirm cards ([058d5ee](https://github.com/vip-pana/wealth-tracker/commit/058d5ee7b4c742a80f8ff22044737b416bd6a65a))
* **advisor:** emit UI widgets from the advisor tools ([99c2627](https://github.com/vip-pana/wealth-tracker/commit/99c2627e848547b4e0f77f5fe0c01f17070b48d5))
* **advisor:** give the advisor full emergency-fund coverage ([a9aa00c](https://github.com/vip-pana/wealth-tracker/commit/a9aa00c222375f84eec2b654efb00bd02ad3e297))
* **advisor:** give the AI advisor callable tools via Prism ([5d6b3c6](https://github.com/vip-pana/wealth-tracker/commit/5d6b3c63750a0763671674c660d9ebd40f5996a7))
* **advisor:** let the AI propose an investor-profile update ([357f714](https://github.com/vip-pana/wealth-tracker/commit/357f714997d4fd211babb1eb9a478c3bce5f3959))
* **advisor:** let the AI run a risk-profiling interview ([6194811](https://github.com/vip-pana/wealth-tracker/commit/61948116666a9e55dc501dc5ea69415aa1222375))
* **advisor:** let the AI update its memory autonomously via remember_fact ([5ef8097](https://github.com/vip-pana/wealth-tracker/commit/5ef809722507e8ef5b81aebbc21cd1358669e315))
* **advisor:** let the interview ask age as human context, not a field ([4ee8251](https://github.com/vip-pana/wealth-tracker/commit/4ee8251b2051ca0e747194c81d059e0c93ed37a2))
* **advisor:** model a growing PAC and show its yearly schedule ([de81570](https://github.com/vip-pana/wealth-tracker/commit/de81570187d572d7ad6e2b00dc900b511969ddba))
* **advisor:** offer a button to generate the proposal instead of asking in words ([14b539f](https://github.com/vip-pana/wealth-tracker/commit/14b539f6dca9959b3d2152a4d0abec6c46cf3301))
* **advisor:** per-milestone target allocation — phase 1 (model + advisor) ([3722205](https://github.com/vip-pana/wealth-tracker/commit/372220574b14a9b28788d7af2bd99b60ad0bfe60))
* **advisor:** personal profile fields (name, birth date/age) and memory ([df41f04](https://github.com/vip-pana/wealth-tracker/commit/df41f047520fb041c42d608706330edb0ef46ce9))
* AdvisorProvider contract + Ollama local provider (layer 2, step 1) ([a7eaa9d](https://github.com/vip-pana/wealth-tracker/commit/a7eaa9d0b481d51722378632aa9d73f958ee82bf))
* **advisor:** render generative UI widgets in the chat ([9ea8b1d](https://github.com/vip-pana/wealth-tracker/commit/9ea8b1d7aee3c8ef9aa57a5aadde42f0dff64715))
* **advisor:** render tabular data as widgets, not ASCII tables ([3215dda](https://github.com/vip-pana/wealth-tracker/commit/3215dda31d214a85ae7766b7b5abb66c110248ad))
* **advisor:** render the profile-proposal confirmation card ([ea449fe](https://github.com/vip-pana/wealth-tracker/commit/ea449fed6706fcc0cd33570a5683a042106d4590))
* **advisor:** render the three new widgets in the chat ([18b0c8a](https://github.com/vip-pana/wealth-tracker/commit/18b0c8a6bc5d9cdf6ab3df2eec1afd6788153515))
* **advisor:** require a real interview before proposing a profile ([8e34192](https://github.com/vip-pana/wealth-tracker/commit/8e34192df8d910893ec090b09118bcaafbd8c2d8))
* **advisor:** require four user turns before proposing a profile ([ec1f248](https://github.com/vip-pana/wealth-tracker/commit/ec1f248de60b0b955a6f26f61ec9caffa5d8dcd5))
* **advisor:** retry a failed chat reply in place ([7e962d0](https://github.com/vip-pana/wealth-tracker/commit/7e962d01f4072bcf46d7c48e371ebba565402685))
* **advisor:** show chat date next to the session title ([09a6f1e](https://github.com/vip-pana/wealth-tracker/commit/09a6f1e839d1b24bc53cfa022937b47eb54bfc07))
* **advisor:** show which tool the AI is using during a chat reply ([c732651](https://github.com/vip-pana/wealth-tracker/commit/c732651073a6c3472007b4dc072cc8744529a22e))
* **advisor:** surface profile notes and add interview triggers ([9cf24b3](https://github.com/vip-pana/wealth-tracker/commit/9cf24b3efff21053104996f8065791a26e02920b))
* **advisor:** tune the local model call (keep_alive, temperature, num_ctx) ([cbfcbb8](https://github.com/vip-pana/wealth-tracker/commit/cbfcbb8888045003ede921439163a9bf8e9e5558))
* **advisor:** update profile facts directly from chat via a confirm card ([8b3a825](https://github.com/vip-pana/wealth-tracker/commit/8b3a825edcedab49e23c01910d05b37ca6c2b558))
* AI Advisor section with on-demand report (layer 2, step 3) ([681b2e8](https://github.com/vip-pana/wealth-tracker/commit/681b2e8a9c5d5cad70069b519948d3c2c7653d6e))
* artisan command to import Scalable transactions (step 4b trigger) ([18954bb](https://github.com/vip-pana/wealth-tracker/commit/18954bb0f34286400e73ad0099144295addad0c9))
* **banking:** ingest bank-account transactions from Enable Banking ([fb34595](https://github.com/vip-pana/wealth-tracker/commit/fb34595a3c663d374e063c34b8cc6f7a77f95c3d))
* build advisor context + generate report action (layer 2, step 2) ([62b4fad](https://github.com/vip-pana/wealth-tracker/commit/62b4fad9f2f4c04e19b0d9295c6013a9c54f32bb))
* capture PAC/single transaction source from Scalable, show in history ([1d2d21c](https://github.com/vip-pana/wealth-tracker/commit/1d2d21c49dc1ff90b2c158ce310cba2b78dbe5b7))
* **cashflow:** add Entrate e Uscite page with batch editing and monthly cards ([efe304a](https://github.com/vip-pana/wealth-tracker/commit/efe304ae928340883a166112ede75ad4b705ebca))
* **cashflow:** auto-classify bank transactions as income/expense/transfer ([4e88061](https://github.com/vip-pana/wealth-tracker/commit/4e880611f79e7fff569aebcb501641816a3f6916))
* **cashflow:** emergency-fund target with months-covered coverage ([3c50dc9](https://github.com/vip-pana/wealth-tracker/commit/3c50dc9e1970c16e4c9e40e9ff8d2824a7776923))
* compute real fun facts about the user's data for the advisor ([7d9890c](https://github.com/vip-pana/wealth-tracker/commit/7d9890c3bcb1abd5bf1a8d18d534e42f7c5bd493))
* ComputePosition — derive shares, avg cost and P&L from transactions (step 2) ([f6f4209](https://github.com/vip-pana/wealth-tracker/commit/f6f4209603854016f3392366e5e1c4e83c027dc3))
* conversational advisor sessions — chat + report, persisted history ([a59be17](https://github.com/vip-pana/wealth-tracker/commit/a59be175aba67cae9493fa7b32e129e1d5089010))
* disconnect the Scalable CLI session from the app ([f52c0bb](https://github.com/vip-pana/wealth-tracker/commit/f52c0bb2c932935af80dcc90de608a3b1d3a8ef1))
* enrich advisor context with costs (TER) and derived PAC contribution ([f6cf9c8](https://github.com/vip-pana/wealth-tracker/commit/f6cf9c820fe00fd4826956f3f4baca169b6409ee))
* generate advisor chat replies in the background ([4116b6b](https://github.com/vip-pana/wealth-tracker/commit/4116b6b75b38235a68b4e5e89e3c428bfba84dfc))
* generate the advisor report in the background (queue + poll + toast) ([2960bb4](https://github.com/vip-pana/wealth-tracker/commit/2960bb4d5afa19eb44c17e2a638d8b0445bb5c1d))
* **goal:** add an 'AI redefine' entry point in the goal edit dialog ([018f4be](https://github.com/vip-pana/wealth-tracker/commit/018f4be3a6a22f2d091f4460516a6b80f636a88a))
* **goal:** per-category absolute cap on milestone allocations ([d382bc5](https://github.com/vip-pana/wealth-tracker/commit/d382bc50647e390b2c407bcd4c14f666bf4e0138))
* **goal:** per-milestone target allocation in the manual form — phase 3 ([326ec9d](https://github.com/vip-pana/wealth-tracker/commit/326ec9d2be0ce8a39a2e59f21e5e2c1532cdb7a4))
* **goal:** per-milestone target allocation with a glide-path bar ([3a14092](https://github.com/vip-pana/wealth-tracker/commit/3a1409249860b1818a264af346080401f07a64fe))
* **goal:** show milestone allocation in the accordion, single-column layout ([5038458](https://github.com/vip-pana/wealth-tracker/commit/503845885341f1811515e7d56097120b00155b8d))
* hide chart values in privacy mode (tooltip off + masked Y axis) ([e396326](https://github.com/vip-pana/wealth-tracker/commit/e3963261c89a0480c0c2594a797115be89bb1d34))
* import Scalable transaction history into transactions (step 4b) ([b247b3b](https://github.com/vip-pana/wealth-tracker/commit/b247b3b14bd53fd1d92f79e5e78c20e24670c01f))
* **input:** reconcile snapshot net worth against the current month's rows ([14027c4](https://github.com/vip-pana/wealth-tracker/commit/14027c4cce0717977f17f576211434d83981f72b))
* investor profile feeds the advisor (layer 2, step 4) ([55931f0](https://github.com/vip-pana/wealth-tracker/commit/55931f08be92422df2bc681b55b47de14ea4a239))
* let the user cancel an in-flight Scalable CLI login ([6e82b4c](https://github.com/vip-pana/wealth-tracker/commit/6e82b4c74e126fd4d24a6a6869b0669f8fba76b9))
* log in to the Scalable CLI from the app via device code ([649849c](https://github.com/vip-pana/wealth-tracker/commit/649849cbec3a9a458fa9e70e5b67f096b305555c))
* make price HTTP clients resilient (timeout, retry, JSON guard) ([9c91cc2](https://github.com/vip-pana/wealth-tracker/commit/9c91cc2e6369c2448e7118f7a12e80b371bb07b6))
* persist the advisor report in localStorage across refreshes ([317ac06](https://github.com/vip-pana/wealth-tracker/commit/317ac0618de039bb40bcde2fe3601965561b20bd))
* persisted in-app notification system with bell + unread feed ([1c2dcfd](https://github.com/vip-pana/wealth-tracker/commit/1c2dcfdda4348cb3ca592e2de5fd4879e9102955))
* portfolio metrics engine + dashboard insights card ([40ca142](https://github.com/vip-pana/wealth-tracker/commit/40ca142032f52e2cdb8c97198693c9ad4574cacc))
* **portfolio:** non-investable emergency-fund buffer, excluded from investment metrics ([77610fc](https://github.com/vip-pana/wealth-tracker/commit/77610fca187bccecc45a9df67c0388eeb2ea27a4))
* price fetch status + restore deleted items, both in Settings ([d27ce75](https://github.com/vip-pana/wealth-tracker/commit/d27ce7571a13423a30fd73c4b1fa280f72e2c03f))
* privacy toggle to hide monetary values ([6319b58](https://github.com/vip-pana/wealth-tracker/commit/6319b58ed9e3be2bb103b8efefa2b669f10d807a))
* read Scalable balances from the official CLI, proxy as fallback ([e63354f](https://github.com/vip-pana/wealth-tracker/commit/e63354f213349b8217d7225ecc2b81ea1d9422f8))
* reconnect Scalable from the app via a "Collega / Riconnetti" button ([e9bfc27](https://github.com/vip-pana/wealth-tracker/commit/e9bfc2778a113838545d30d160ba8c1881429ef5))
* rename advisor sessions inline ([d6a8b90](https://github.com/vip-pana/wealth-tracker/commit/d6a8b90bf4ef3b1bb2ec8b6e94de3031119370e9))
* replace the unused Analisi section with an Investimenti section ([1335fb6](https://github.com/vip-pana/wealth-tracker/commit/1335fb6cd43cf5ac081ba72a69bbeb8e4b505325))
* report which prices failed to refresh instead of always succeeding ([dc1b4af](https://github.com/vip-pana/wealth-tracker/commit/dc1b4af7fdc994f41d79b183f5876322ded7a1ca))
* Scalable Capital balance sync (stopgap) with connection-health UX ([3066f89](https://github.com/vip-pana/wealth-tracker/commit/3066f89e06a1eef67d03879ae2b4619b451d13a7))
* **scalable:** keep the CLI session alive with a 6-hourly ping ([4cf8e81](https://github.com/vip-pana/wealth-tracker/commit/4cf8e81f05b6208cd0fe976b33393e6597229155))
* show milestone checkpoints on the goal progress bars ([ad4ac47](https://github.com/vip-pana/wealth-tracker/commit/ad4ac47a2a6fca403503082ec3f1f31a3f2170f0))
* **snapshots:** daily automatic snapshot, only when every source is fresh ([6ad0bb7](https://github.com/vip-pana/wealth-tracker/commit/6ad0bb7a7c1de5445b4dfe5a22129e30e0a3c2b8))
* stream advisor chat replies token-by-token ([20c7ced](https://github.com/vip-pana/wealth-tracker/commit/20c7ced3e0e5a9431561031f14f9a7902b220690))
* stream chat UI + skip refresh on active session click ([a4687c2](https://github.com/vip-pana/wealth-tracker/commit/a4687c221ef018db24f2941a89effd8ee227ecb2))
* surface Scalable connection health and harden the ISIN link ([1172d28](https://github.com/vip-pana/wealth-tracker/commit/1172d2807898aa6a0b44490846ca7dff1191472e))
* sync Scalable Capital balances into assets (stopgap via host proxy) ([63a429a](https://github.com/vip-pana/wealth-tracker/commit/63a429a4651a88bd4db7f0348db3a4e2e8923a14))
* transaction-managed assets sync quantity and lock manual edits (step 3) ([b7d1f5c](https://github.com/vip-pana/wealth-tracker/commit/b7d1f5cb3f34531a1615431cb009b5acb14bc3c8))
* transactions table, model and factory (step 1 of share-level positions) ([05b18f0](https://github.com/vip-pana/wealth-tracker/commit/05b18f0dfdedc80014da733e68ef045c83654bb5))
* true per-position return from transactions (step 5) ([274be73](https://github.com/vip-pana/wealth-tracker/commit/274be7395bd9400d5d2b10b35bb9ae08cc16eb59))
* two-column goal edit dialog + shared OptionalHint ([d85f6a3](https://github.com/vip-pana/wealth-tracker/commit/d85f6a3d1ff652c21913391dc749d5a7d1cb7a85))
* unlink an asset from its transactions in Settings ([a42d21f](https://github.com/vip-pana/wealth-tracker/commit/a42d21face44cf3ad943681c0bb7cef4a833258a))
* view-only transactions dialog per asset (step 4) ([a417cfa](https://github.com/vip-pana/wealth-tracker/commit/a417cfaa4d10fbe7cde0f63effacb2949ebfd1cf))
* warn on a likely-typo value at asset entry ([14eb997](https://github.com/vip-pana/wealth-tracker/commit/14eb997b777e0264626f8cc53f07b8eb53a2d7e8))
* **wip:** Enable Banking consent flow UI (phase 3) ([a49b626](https://github.com/vip-pana/wealth-tracker/commit/a49b626cec977f5739fe54eabdf5f1ce4ce2d8ef))
* **wip:** GoCardless open-banking client (phase 1, read-only) ([26d91bf](https://github.com/vip-pana/wealth-tracker/commit/26d91bfac2b54bba8ccf7914e964b182cedf1405))
* **wip:** link a manual asset to a GoCardless bank account (phase 2) ([59da45c](https://github.com/vip-pana/wealth-tracker/commit/59da45cb071d22a66ca942ac5f027d5c55c5f0c1))
* **wip:** lock the value field for bank-linked assets in the input modal ([26ee5e3](https://github.com/vip-pana/wealth-tracker/commit/26ee5e3e9a1dbfb20938f11383703aeddd74e4e7))
* **wip:** one-command tunnel helper for the bank consent flow ([21f7137](https://github.com/vip-pana/wealth-tracker/commit/21f713774926709d462b617e385a1a8581cb3c11))
* **wip:** polish the bank-connect UX (reassurance, tunnel hint, account names) ([b38ace8](https://github.com/vip-pana/wealth-tracker/commit/b38ace83beba4317b0b64a656882309d71de6bd7))
* **wip:** real Riconnetti action + show when an asset's value comes from a bank ([beee733](https://github.com/vip-pana/wealth-tracker/commit/beee733cc5fad11a428122e02f86c4bcc7651d0c))
* **wip:** switch open-banking integration from GoCardless to Enable Banking ([53ca32d](https://github.com/vip-pana/wealth-tracker/commit/53ca32d243f70ac4eaf18d8c860ec88add086279))


### Bug Fixes

* **a11y:** add DialogDescription to every dialog ([18394b7](https://github.com/vip-pana/wealth-tracker/commit/18394b77b68771ed0dd63af5769dfb87bd1f7a15))
* actually run the scheduler so daily price refresh happens ([4bb87da](https://github.com/vip-pana/wealth-tracker/commit/4bb87da627c7180c0fcfaca364c6d6356e41295a))
* advisor completion toast now fires reliably on pending→done ([abbe0b9](https://github.com/vip-pana/wealth-tracker/commit/abbe0b905e13f57b99380e8798fd6d02ee720aa4))
* advisor toast — drive it off the status state, not the poll closure ([cf749a1](https://github.com/vip-pana/wealth-tracker/commit/cf749a145ffedefbb16b7887e372c3dd41f16499))
* **advisor:** 5 goal-interview defects from session 112 ([4284d70](https://github.com/vip-pana/wealth-tracker/commit/4284d707f50adda466f3397481b28ac614f5da39))
* **advisor:** answer the user's questions before continuing the interview ([920792e](https://github.com/vip-pana/wealth-tracker/commit/920792ea89c3331b04b1c0da259a73e249002483))
* **advisor:** bind a configured provider in ProposeControllerTest ([4970c11](https://github.com/vip-pana/wealth-tracker/commit/4970c11d333397b81b4e49ac4d074aa698b14c82))
* **advisor:** deepen the risk interview and stop compulsive proposing ([089c41b](https://github.com/vip-pana/wealth-tracker/commit/089c41b24710177b65b0f52594e0a69022574322))
* **advisor:** don't ask to update the profile early in the interview ([f8bc759](https://github.com/vip-pana/wealth-tracker/commit/f8bc75952e271a9a60c079f844d9179595ad7061))
* **advisor:** don't re-offer an already-applied profile proposal ([d28286d](https://github.com/vip-pana/wealth-tracker/commit/d28286d8813acd67897453a8ad3672a5b9ff391b))
* **advisor:** expose the goal's milestones and description in the context ([0770c9f](https://github.com/vip-pana/wealth-tracker/commit/0770c9fcd05e16a8811b15770a1556581327a585))
* **advisor:** gate profile proposals behind explicit user consent ([d21c72a](https://github.com/vip-pana/wealth-tracker/commit/d21c72aee68d1a11a0fda2c95044cef5d62a3155))
* **advisor:** goal simulator uses the backend month count so it matches the prose ([c619001](https://github.com/vip-pana/wealth-tracker/commit/c619001eaf82ef1e262bc67f83e5e34b31c8b976))
* **advisor:** harden tool use — no recited calls, cover non-tx positions ([92e4b79](https://github.com/vip-pana/wealth-tracker/commit/92e4b79fcd602cef485b1c9f7fa36dcbe61dc116))
* **advisor:** hide the 'nothing saved until Applica' hint after applying ([c4b5e6b](https://github.com/vip-pana/wealth-tracker/commit/c4b5e6b5f7b3f9900b798b91928959cdbccfcd42))
* **advisor:** keep only the last profile proposal per reply ([f206fee](https://github.com/vip-pana/wealth-tracker/commit/f206feeb775858c3433d0e66455f00fe7fa6f574))
* **advisor:** make goal/profile interview flow reliable and button-only ([3ab5f11](https://github.com/vip-pana/wealth-tracker/commit/3ab5f113a5e24182bddc7b6bf17362932fc11307))
* **advisor:** make the chat usable on phones and tablets ([259aad7](https://github.com/vip-pana/wealth-tracker/commit/259aad70a0c68283af89dea0478ee77cc5158b9b))
* **advisor:** make the Goal the single source of objective + target allocation ([c649e4f](https://github.com/vip-pana/wealth-tracker/commit/c649e4f29aa8483699880d8ca1cae048b8a02788))
* **advisor:** make the model quote the simulator figures exactly ([4939826](https://github.com/vip-pana/wealth-tracker/commit/4939826b0273226bf316fdc422586c9366bfd2a2))
* **advisor:** make the proposal button track the live conversation ([31ff946](https://github.com/vip-pana/wealth-tracker/commit/31ff9465e8cf84861b006025e4d53b1eda8c92f4))
* **advisor:** make the risk interview multi-turn and deeper ([3e174e6](https://github.com/vip-pana/wealth-tracker/commit/3e174e6c89e191c438f95601b7c689598d6816c9))
* **advisor:** make the risk-profiling interview actually ask first ([9175f3e](https://github.com/vip-pana/wealth-tracker/commit/9175f3e4e4769fc5fa1ac5c916f0ccd4aefdeed6))
* **advisor:** move the queue to its own SQLite file to stop DB locks ([16b2d7b](https://github.com/vip-pana/wealth-tracker/commit/16b2d7bd51f7ffe191badc85f6dac85d69e1c5e5))
* **advisor:** nudge the model to call the propose tool once consent is given ([4de5f45](https://github.com/vip-pana/wealth-tracker/commit/4de5f45a509721456ac6621dad25af681ba8eab6))
* **advisor:** re-sync the profile dialog when the profile prop changes ([ea158e4](https://github.com/vip-pana/wealth-tracker/commit/ea158e4fe34b1610cc9130124fb0c48edf935faa))
* **advisor:** reopening a session must exit "new conversation" mode ([e9d17cc](https://github.com/vip-pana/wealth-tracker/commit/e9d17ccbd4aec7629ec7ad5335c661676d000018))
* **advisor:** retry a failed proposal turn by re-running the proposal ([0a9beeb](https://github.com/vip-pana/wealth-tracker/commit/0a9beebd7fee26159f1487afe338ec44539b3adc))
* **advisor:** retry empty replies and lower Regolo temperature ([ae74fe3](https://github.com/vip-pana/wealth-tracker/commit/ae74fe37e788955e24d0581f2ba7a44d7c083104))
* **advisor:** show the proposal button as a deterministic fallback ([0818339](https://github.com/vip-pana/wealth-tracker/commit/081833963bdb4c55b5d21b64f6a033e1a0b0734a))
* **advisor:** targeted proposals + richer milestones + implicit allocation intent ([1c769ac](https://github.com/vip-pana/wealth-tracker/commit/1c769ac3243fb744017d1b08416b09cf441978e3))
* **advisor:** the AI is the advisor — never punt to "a financial advisor" ([08889e1](https://github.com/vip-pana/wealth-tracker/commit/08889e168e94fc2165510a7cb01a9df287811c5a))
* **advisor:** the risk interview reads the data before asking ([a9118e8](https://github.com/vip-pana/wealth-tracker/commit/a9118e8658c713b2a989bc00936f5faae6dfcdfd))
* **advisor:** tolerate a DB without the snapshots table when building tools ([7a59344](https://github.com/vip-pana/wealth-tracker/commit/7a59344945a620ee3702b311f6183e61728b5516))
* **advisor:** tolerate milestone widgets saved before per-milestone allocation ([06e9c23](https://github.com/vip-pana/wealth-tracker/commit/06e9c2347556964d2ddc14cf9b1d691963533169))
* annotate advisor context + tighten the ethical boundary (layer 2) ([9eed8a7](https://github.com/vip-pana/wealth-tracker/commit/9eed8a7c2aaf89941dc928911b1439016fac77e2))
* degrade gracefully when the Scalable proxy is unreachable ([55e76e5](https://github.com/vip-pana/wealth-tracker/commit/55e76e59bf2f91b70e45b18e9a7e32a83ac7b477))
* enable SQLite WAL + busy_timeout to stop "database is locked" ([0401d56](https://github.com/vip-pana/wealth-tracker/commit/0401d56e2b6135bf0050e25e372852075e76ac35))
* **goal:** show and preserve milestone action/rationale in the manual form ([93c853b](https://github.com/vip-pana/wealth-tracker/commit/93c853b432743d75a2b28dc26c1cc55aa382ccab))
* lock the quantity field for transaction-managed assets in the form ([b98c2c0](https://github.com/vip-pana/wealth-tracker/commit/b98c2c0afc01cf167c04e8df7319e2c7b1affc01))
* make the queue worker resilient (auto-restart) so jobs never stall ([102c2db](https://github.com/vip-pana/wealth-tracker/commit/102c2db51ee661310edf1fadd5637fa084e9fc27))
* mask amounts passed as string props (dashboard summary, insights, tx dialog) ([6e510c0](https://github.com/vip-pana/wealth-tracker/commit/6e510c0ce36802ba9590820e100942da4abc0142))
* mask the allocation donut legend values and tooltip in privacy mode ([a819975](https://github.com/vip-pana/wealth-tracker/commit/a8199750f1622cd22c5326804a1ca5cf19cd1665))
* **notifications:** dismiss advisor notification when its session is opened ([b04fc27](https://github.com/vip-pana/wealth-tracker/commit/b04fc27397eb2fbc5b3d2c97fd8bed7f0cf75e06))
* **notifications:** resolve the Scalable sync-failed warning on reconnect ([5a6d736](https://github.com/vip-pana/wealth-tracker/commit/5a6d73631abdaf1bcbc936698c12e3422ef29013))
* **prices:** backfill ETF TER from the .MI symbol, not just the bare ticker ([020d84f](https://github.com/vip-pana/wealth-tracker/commit/020d84f315ec42eda59c35bffd0c99ca910fd96a))
* **queue:** stop advisor replies from failing under a slow local model ([58c017f](https://github.com/vip-pana/wealth-tracker/commit/58c017f7640e2476cc73405acb750ab788f7bf04))
* raise queue:listen timeout above the Scalable login job's 900s ([a0e3ea0](https://github.com/vip-pana/wealth-tracker/commit/a0e3ea0a68b0bf37ec282121dde7e262e79b84ce))
* render all markdown heading levels (#### was shown as literal text) ([3d9d6ac](https://github.com/vip-pana/wealth-tracker/commit/3d9d6ac74c731fbd91972855aa9b8a27326bffc9))
* show the sent question optimistically when starting a new chat ([4eff197](https://github.com/vip-pana/wealth-tracker/commit/4eff19773b6770dc0b86debaa821aa566fa330a5))
* trust proxies only when a TLS tunnel is configured ([01be8ae](https://github.com/vip-pana/wealth-tracker/commit/01be8ae1da0a99b793192d7aedb731dd7e11949f))
* **ui:** do not re-navigate when clicking the already-active sidebar link ([ae5e41a](https://github.com/vip-pana/wealth-tracker/commit/ae5e41ad05c1b01d0b55c4acd9438270d0ab232d))
* **ui:** scrollable session list and suggestion chips, tighter chart legend ([03dd13a](https://github.com/vip-pana/wealth-tracker/commit/03dd13a35e359a2689c65b20efc77f1f6c246148))
* use axios for the generate POST so CSRF passes (was 419) ([5024f1e](https://github.com/vip-pana/wealth-tracker/commit/5024f1e943c62cc439ff13c048e5838d7e7c30f1))
* **wip:** align asset list bank signals with the live link state ([19725cc](https://github.com/vip-pana/wealth-tracker/commit/19725cc6255415c654f7cd661edd5bd7f38420bf))
* **wip:** bank link follows the asset across months, not pinned to one row ([92af57e](https://github.com/vip-pana/wealth-tracker/commit/92af57e8c41a818f2411a8ae892f5f80fe21afcf))
* **wip:** block deleting a category still linked to a bank account ([979fa9d](https://github.com/vip-pana/wealth-tracker/commit/979fa9d70a0560b62bb6f67bee48768c0aef113f))
* **wip:** connect to a bank on the first click, not the second ([2325cfb](https://github.com/vip-pana/wealth-tracker/commit/2325cfbf2aec1ba1d6b4a1a8f9f1c345ada42afa))
* **wip:** enforce the bank-link identity lock server-side and stop delete from duplicating ([ff2f099](https://github.com/vip-pana/wealth-tracker/commit/ff2f09979c961c70883c8750b30aa5297ded72c0))
* **wip:** hide manual/ticker toggle for bank-linked assets ([290b53c](https://github.com/vip-pana/wealth-tracker/commit/290b53cfbb3cf5fb19d6ccc9ed2fb4a93d68da63))
* **wip:** lock category for bank-linked assets, restrict linkable list to liquidity ([fa7271a](https://github.com/vip-pana/wealth-tracker/commit/fa7271aa2558056842ee4936f9bd4a989450e457))
* **wip:** lock the name field too for bank-linked assets ([13165ad](https://github.com/vip-pana/wealth-tracker/commit/13165ad2ba5651b0cb49bc1204dc476abdfa5ec4))
* **wip:** make disconnect honest and warn before deleting a bank-linked asset ([2ae59d7](https://github.com/vip-pana/wealth-tracker/commit/2ae59d74aa9b958ab8c04fbc0327ba6168b12739))
* **wip:** make the bank consent flow work behind an HTTPS tunnel ([605cda6](https://github.com/vip-pana/wealth-tracker/commit/605cda679ce484f16f392099872d01123662fa96))
* **wip:** persist bank-sync status per account, like asset prices ([c8818c2](https://github.com/vip-pana/wealth-tracker/commit/c8818c2c1baa993a6976b864d5065e63cfdaa327))
* **wip:** polish the Settings banking card copy and feedback ([3459c9a](https://github.com/vip-pana/wealth-tracker/commit/3459c9a057af2d836868e09749f1bb56d040ea2c))
* **wip:** reconnecting a bank keeps the account→asset link (inherit by IBAN) ([f1bdbd7](https://github.com/vip-pana/wealth-tracker/commit/f1bdbd7e17112cbc14a9b3b6816ea624983a97df))
* **wip:** refresh live values before a today-dated snapshot ([c2ce32c](https://github.com/vip-pana/wealth-tracker/commit/c2ce32ceee5b8e715a8d3edb456677abf5f6cd70))
* **wip:** show last-sync time in the modal and notes for bank-synced rows ([677660f](https://github.com/vip-pana/wealth-tracker/commit/677660f32764d476f68f39efb4fa1c413f4d84ee))
* **wip:** show per-account last-sync freshness in the banking card ([a34d63a](https://github.com/vip-pana/wealth-tracker/commit/a34d63a94f3d3b639a1e7f295e50bc3264b3fd76))
* **wip:** show the manual/ticker toggle only when creating an asset ([1548d24](https://github.com/vip-pana/wealth-tracker/commit/1548d240535d64d4b31b00874bc5d1a89393b80a))
* **wip:** track real bank-consent validity and expire on rejection ([0cd1ccc](https://github.com/vip-pana/wealth-tracker/commit/0cd1ccc676be4fcf63d8c765791fa3e5db02d25b))
* **wip:** use emerald for the prices 'Aggiornato' status dot ([add0d29](https://github.com/vip-pana/wealth-tracker/commit/add0d2976340ee1644c0a2c1291f0be9c36ead83))
* **wip:** warn that 'Rimuovi collegamento' drops the asset links ([8f8f8b7](https://github.com/vip-pana/wealth-tracker/commit/8f8f8b779e7eda696ef02752e8a65a48c9a281cd))


### Refactoring

* **advisor:** drop qualitative emergency_fund, use the real tagged buffer ([8eb38e5](https://github.com/vip-pana/wealth-tracker/commit/8eb38e5c05ac6d9c0274c32f36e3a2cd60e9a4ba))
* **advisor:** use observed net income, drop hand-entered income_monthly ([a8cac27](https://github.com/vip-pana/wealth-tracker/commit/a8cac27844124907a91b014b690aff1bde79d402))
* extract stepMonth month arithmetic into a lib helper ([8b8a34f](https://github.com/vip-pana/wealth-tracker/commit/8b8a34fc07d75fa8483c5b6928b66252e1aed912))
* **goal:** give the milestone label an explicit heading ([0d8277e](https://github.com/vip-pana/wealth-tracker/commit/0d8277e6aedf249ff2fc2f4b09bf4251de0af51e))
* remove the unused category emoji (icon) field ([1fb5712](https://github.com/vip-pana/wealth-tracker/commit/1fb5712f1e5b881b5de2a890b4857d6f5b093383))
* rename bank_synced_at to synced_at, add explicit sync_source ([4e12f7c](https://github.com/vip-pana/wealth-tracker/commit/4e12f7cd98718aeef461633eb968c0ad00683336))
* split large advisor/settings/goal/pension pages into components ([b7946a4](https://github.com/vip-pana/wealth-tracker/commit/b7946a487b4f6c985d3da6785f997d2af145f2dc))

## [1.0.0] - 2026-05-30

First stable release. A single-user personal wealth tracker: log assets by
category each month, take point-in-time snapshots, and watch net worth evolve
through charts, forecasts and goals.

### Tracking & input
- Log assets per category each month, by manual value or by ticker + quantity.
- Live prices for ETFs (Yahoo Finance) and crypto, refreshed daily and on demand.
- Each ticker row shows how fresh its price is and flags prices older than 24h or missing.
- Copy a whole month's assets forward when starting a new month.
- Fondo Pensione tracked as an annual, year-end illiquid asset on its own page.

### Snapshots & net worth
- Take a snapshot any day (not just monthly); each is a dated photo of net worth.
- Current net worth shown live on the input page.
- A snapshot freezes per-category values, so historical charts never drift.

### Analysis & goals
- Dashboard: net-worth line, allocation donut, stacked bars, growth rate,
  month-over-month comparison and a forecast, each at category or macro level.
- Analysis page with category and date-range filters, pagination and CSV export.
- One savings goal with target value, target date, per-category allocation
  targets and milestones, plus a current-vs-target allocation comparison.

### Data safety
- Soft delete across assets, categories, goals and pension entries.
- Undo a deletion straight from the toast.
- Automatic database backup nightly, after every snapshot, and on demand,
  using atomic SQLite snapshots synced to cloud storage.
- CSV import/export with a documented template.

### Interface
- Light and neutral dark themes, collapsible sidebar.
- Responsive across desktop and mobile (drawer navigation, stacked cards,
  mobile-friendly tables and dialogs).

### Quality & tooling
- Laravel 12 / PHP 8.4 backend, React 19 + TypeScript (strict) via Inertia.js.
- PHPStan level 9, Laravel Pint, ESLint and TypeScript strict all enforced.
- 106 PHP tests and 53 frontend tests covering the money paths
  (valuation, forecast, growth, CSV import/export, snapshot state, dashboard builders).
- A shared pre-push hook and GitHub Actions CI run the full gate in Docker.

[1.0.0]: https://github.com/vip-pana/wealth-tracker/releases/tag/v1.0.0
