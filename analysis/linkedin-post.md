# LinkedIn post (ready to paste)

> Sanitized for public posting: no wallet address, no attacker delivery domain, no
> client/victim data. Full technical detail + IOCs live in the GitHub writeup.
> Links below are live once the repo is pushed and GitHub Pages is enabled.

---

🚨 A WordPress server pinned at 100% CPU. The cause wasn't a bug in the code — it was someone mining Monero on our client's hardware.

Here's the incident, the reverse-engineering, and the twist: I rebuilt the whole attack as a safe, reproducible lab so the community can learn from it. 🧵

🔍 THE INVESTIGATION

The symptom was boring: sustained CPU exhaustion. The cause was a full cryptojacking operation running inside the WordPress environment. Reconstructing the chain:

Vulnerable plugin → unauthenticated upload
        ↓
Web shell (remote command execution)
        ↓
PHP loader / dropper
        ↓
XMRig miner pulled in 11 chunks
        ↓
Mining to a public pool on the web server's CPU

Three artifacts did the work:
📌 wp-loader.php — a web shell (auth + file upload + shell_exec)
📌 wp-helper.php — the loader that downloads and babysits the miner
📌 wp-worker.exe — the miner itself (a Linux ELF wearing a .exe name)

🧪 WHAT REVERSE-ENGINEERING THE MINER REVEALED

The interesting tradecraft wasn't the miner — it was how it was delivered:

▪️ It's stock XMRig 6.26.0 — but self-compiled on Alpine/musl. Recompiling gives it a brand-new hash, so signature-only AV that only knows the official release never fires.
▪️ No pool or wallet baked into the binary. All config is passed at runtime by the loader — one clean, reusable binary, per-victim configuration.
▪️ Delivered in 11 fragments, not one 8 MB file — quieter, harder to block.
▪️ Persistence lived entirely in HTTP. No cron. No systemd. The attacker just re-requested the loader every few hours to restart a dead miner.

⚠️ ROOT CAUSE

❌ A vulnerable, internet-exposed plugin
❌ PHP able to write executables into the web root (FS_METHOD=direct + over-permissioned user)
❌ No egress filtering — the host could freely reach both the payload host and the mining pool

🛡️ WHAT ACTUALLY STOPS THIS

🔒 Remove/patch vulnerable plugins (kills initial access)
🔒 Run WordPress as a non-owning user; drop FS_METHOD=direct (kills the file write)
🔒 Deny PHP in uploads/ and executables under wp-content/ (kills execution)
🔒 Egress-filter outbound mining ports (kills the payoff)
🔒 File-integrity monitoring + CPU/network baselining (catches it early)

🧰 THE PART I'M MOST PROUD OF

I reproduced this end-to-end as an isolated Docker lab — a REAL XMRig, but on a network with no route to the internet, mining into a fake local pool. It ships an attacker walkthrough, a defender IR guide, and detection exercises. Safe to run, nothing leaves the box.

Because the best way to defend against an attack is to be able to rebuild it.

💬 Takeaway: a WordPress site exposed to the internet is an application server. Treat it like one. Security isn't only prevention — it's detection, investigation, and recovery.

🔗 Full technical writeup + IOCs + YARA: https://zenithgenius.github.io/wordpress-xmrig-cryptojacking/analysis/xmrig-wordpress-cryptojacking.html
🔗 Reproducible lab + repo: https://github.com/ZenithGenius/wordpress-xmrig-cryptojacking

#CyberSecurity #IncidentResponse #WordPressSecurity #ThreatHunting #MalwareAnalysis #DigitalForensics #DevSecOps #BlueTeam #XMRig #Cryptojacking
