# Massive DHCP reservetion script for pfSense
A custom PHP script for massive add/updates of DHCP reservation entries in pfSense

## Introduction
In many modern IT environments, DHCP reservations are used to assign a "permanent" address to a set of devices, instead of configuring one by one on static addresses.

The pfSense GUI is great, but when you have to add or modify over 20 DHCP entry, the manual work becomes unbearable and increases the possibility of errors.

Because of a lack of native massive-update functions in pfSense, I've decided to wrote a PHP script (to be used in the PHP shell) in order to add and modify massively the DHCP reservations.

## Coding concepts
I've reverse-engineered the PHP code of the DHCP Server section of the pfSense GUI. My script is as near as possible as the actions done by pfSense itself to update DHCP reservations.
I've skipped the CID part because I've never used it for my purposes.

The script includes ```globals.inc``` and ```util.inc``` from pfSense.
I've just copied two function from another pfSense source for semplicity.

## How to use
First of all, be aware that my script works on one interface at a time!

Set the interface name (```$if``` variable) to the technical name of the pfSense interface (example: ```opt1```), not to the GUI name (example: ```Guests```)!
You can get the technical interface name by the link, navigating to the DHCP Server > Interface (example: ```/services_dhcp.php?if=opt1```).

#### Procedure:

1. Copy/paste the script to a text editor
2. Edit the ```$if``` variable at the end of all functions declaration with the interface name (as explained before)
3. Generate the ```addReserv``` calls, filling all the text fields you need (Desciption is optional, Hostname is optional if you aren't using DNS registration of DHCP entries). **Obviously, delete my example entries before run**
4. Login via SSH to your pfSense machine
5. Enter the PHP Shell with "12" choice after the prompt
6. Copy/paste the whole modified script from your text editor, **excluding the** ```<?php``` **and** ```?>``` **lines** (inserted just to have PHP highlighting in advanced text editor)
7. After the script, launch the ```exec``` command in the shell
8. Verify the output of the script for any error
9. Go to the DHCP Server of the interface from the GUI
10. Verify the entries created and then click the "Apply changes" button at the top

#### Add VS update
If the MAC address you are processing does not exist in the config, the entry gets added.
Instead, if the MAC address is already present, the script will update the record.


## Limitation and testing
The script is tested on pfSense 2.6.0
I recomend to make a backup of the config before use the script!

## Support me
I'm glad to share this code to other IT technicians for free.

If you found my work useful and want to support me, you can donate me a little amount  
[![Donate](https://img.shields.io/badge/Donate-Paypal-2997D8.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=JA8LPLG38EVK2&source=url)
