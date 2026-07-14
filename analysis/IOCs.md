# Indicators of Compromise — WordPress XMRig Cryptojacking

Attacker indicators are published for threat-intelligence value and **defanged**
(`[.]`, `hxxp`). Victim data is intentionally omitted. Refang before use in tooling.

## Files (host)

| Path (relative to web root) | Type | Role |
|---|---|---|
| `wp-content/wp-worker.exe` | ELF x86-64 | XMRig 6.26.0 miner (renamed) |
| `wp-content/wp-helper.php` | PHP | Loader / dropper / keepalive |
| `wp-content/wp-loader.php` | PHP | Web shell (Basic auth) |
| `wp-content/wp-worker.part1` … `.part11` | data | Transient miner fragments during download |

## Hashes

```
SHA-256  b20f39fc00d242e706b6c30367ad811c676e0575050a4ec2f30104b696944b49   wp-worker.exe
MD5      e1b3e738928012a07dfce8659b3ff31d                                   wp-worker.exe
BuildID  c746d5445679e29ea09a8ae5bdc7fbbbf3720c44 (ELF sha1)
```

Note: the binary is self-compiled (Alpine/musl, GCC 13.2.1), so the hash is
specific to this build. Hunt on behavior + strings, not hash alone.

## Network

```
Mining pool:     pool.supportxmr[.]com:3333            (RandomX / rx-0)
Delivery CDN:    hxxp://lingering-fog-3211.darleenvondohlen10.workers[.]dev/
                 └─ serves wp-worker.part1 .. wp-worker.part11
Attacker wallet: 43mfU2BozuxbowW715FsM98Sh3jWMcEiXYFLVpHiMYvWP3B3rmEVpT8GkTzeYF7E44eurXuRSnRwkLGVbU7NvCsJEzXv2eJ  (Monero)
```

## Web-server log patterns

```
POST /wp-content/plugins/wp-file-manager/lib/php/connector.minimal.php   -> upload (CVE-2020-25213)
GET  /wp-content/wp-loader.php                                            -> web shell (401 then 200)
GET  /wp-content/wp-helper.php                                            -> loader / recurring keepalive
GET  /wp-content/wp-worker.part[1-11]                                     -> chunked miner delivery
```

## Process

```
<web-user>  <pid>  <apache-ppid>  .../wp-worker.exe -o <pool>:3333 -u <wallet> -t <nproc/2> -p <cpu-model>
parent:     apache2 / php-fpm     (spawned by PHP, not cron/systemd)
```

## MITRE ATT&CK

`T1190` Exploit Public-Facing Application · `T1505.003` Web Shell ·
`T1105` Ingress Tool Transfer · `T1496` Resource Hijacking ·
`T1071.001` Application Layer Protocol: Web Protocols

## YARA — PHP loader/shell

```yara
rule WP_XMRig_Loader_Webshell
{
    meta:
        description = "WordPress XMRig cryptojacking loader + web shell"
        author      = "Isaac Joumessi"
        date        = "2026-07-14"
        reference   = "analysis/xmrig-wordpress-cryptojacking.md"
    strings:
        $s_worker   = "wp-worker" ascii
        $s_part     = "wp-worker.part" ascii
        $s_setsid   = "setsid nohup" ascii
        $s_nproc    = "shell_exec('nproc')" ascii
        $s_isrun    = "isXmrigRunning" ascii
        $sh_exec    = "shell_exec($_POST['command'])" ascii
        $sh_auth    = "WWW-Authenticate: Basic realm" ascii
    condition:
        // loader
        (2 of ($s_*)) or
        // web shell
        (all of ($sh_*))
}
```

## YARA — the miner (generic XMRig)

```yara
rule XMRig_Miner_Generic
{
    meta:
        description = "XMRig Monero miner (stock strings)"
        author      = "Isaac Joumessi"
    strings:
        $v   = "XMRig 6." ascii
        $a1  = "rx/0" ascii
        $a2  = "randomx" ascii nocase
        $d   = "dev donate started" ascii
        $api = "api.xmrig.com" ascii
    condition:
        uint32(0) == 0x464c457f and $v and 2 of ($a1,$a2,$d,$api)
}
```
