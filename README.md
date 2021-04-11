# leobot
crypto bot

ATTENZIONE: software non testato completamente e mancante di numerosi controlli.i Usate a vostro rischio e pericolo.

Applicazione che implementa un grid bot per l'exchange The Rock Trading (https://www.therocktrading.com/referral/557) N.B. il link contiene il mio referral code. 

L'applicazione è stata sviluppata in ambiente Linux, per farla girare su altri S.O. necessita di adattamento.

E' composta di un set di classi e di due script:

- gridbot.php crea il bot e lo avvia

- restoregrid.php riavvia un bot interrotto.

Il bot invia un messaggio a un canale telegram per ogni ordine che viene eseguito, se non si dispone di un canale telegram è sufficiente rendere inoperativa la funzione di invio.

Requisiti:
- Mysql
- php-cli

E' necessario disporre di API KEY dispositive.
La configurazione prevede di editare e copiare sotto /etc/gridbot i file
headers.inc.php
telegram.inc.php
in cui sono memorizzate le chiavi di sicurezza per TRT e Telegram.

Va inoltre editato il file config.php per inserire i dati relativi al DB mysql

Il DB mysql ha solo 2 tabelle e può essere creato con lo script creadb.sql

Utilizzo:
php gridbot.php numero_grid coppia qta_singolo_ordine min_intervallo max_intervallo nomebot

Esempio di utilizzo:
php gridbot.php 10 BTCEUR 0.001 500 1000 btcbot01

Crea un bot con 10 ordini (5 buy e 5 sell), ciascuno per 0.001 BTC, con intervallo fra gli ordini che va da 500 EUR a 1000 EUR. 
Il bot parte dalla media tra bid e ask e imposta gli ordini utilizzando min_intervallo e aumentandolo progressivamente fino a max_intervallo.

Se il lancio viene interrotto per qualsiasi motivo è possibile far ripartire il bot con lo script restorebot.php.

Utilizzo:
php restorebot.php nomebot

Esempio:
php restorebot.php btcbot01


