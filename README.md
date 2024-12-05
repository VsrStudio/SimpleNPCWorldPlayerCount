# WorldPlayerCount 
A simple PocketMine addon plugin for SimpleNPC which allows you to create a SimpleNPC counting the players of a world(s) on its name tag.<br>
## Versions
- v1.0-beta
  - First version

- v2.0-beta
  - Added combined world player count support!
  - Added customizable count-check interval, see the config
  - Remake To Api5 Plugins @VsrStudio
## Usage for single world
__Note: This is used when creating a snpc counting the players of 1 world only__
- First thing we add after the entity type (human) is the nametag we want, followed by a {line} tag then adding "count WORLDNAME"<br><br>
- __Example : /snpc spawn human Hub{line}count Hub<br>__<br>
- Congrats!, you spawned an entity counting the players of the world "Hub"
## Usage for combined worlds
__Note: This is used when creating a snpc counting the players of more than 1 world__
- First thing we add after the entity type (human) is the nametag we want, followed by a {line} tag then adding "combinedcounts World1&World2" and so on with the "&" symbol<br><br>
- __Example : /snpc spawn human SkyWars{line}combinedcounts SK-1&SK-2&SK-3__<br><br>
- Congrats!, you spawned an entity counting the players of the worlds "SK-1", "SK-2" and "SK-3" at the same time
## Config
- In the config you can customize the name tag of the snpc, it is set to "{number} Playing" by default<br>
- The count "check" interval is also customizable and it is set to 1 second by default
## Contacts
In case you are confused about the usage or found a bug please contact me on my discord __@кнαℓє∂#7787__
