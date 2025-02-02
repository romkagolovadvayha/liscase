const SteamUser = require('steam-user');
const SteamCommunity = require('steamcommunity');
const SteamTotp = require('steam-totp');
const TradeOfferManager = require('steam-tradeoffer-manager/lib/index.js'); // use require('steam-tradeoffer-manager') in production
const FS = require('fs');
const logger = require("./logger");

let client = new SteamUser();
let manager = new TradeOfferManager({
    "steam": client, // Polling every 30 seconds is fine since we get notifications from Steam
    "domain": "prostoj.store", // Our domain is example.com
    "language": "ru", // We want English item descriptions
    "useAccessToken": true // We want English item descriptions
});
let community = new SteamCommunity();

// Steam logon options
let logOnOptions = {
    "accountName": "romkadvayha",
    "password": "2580422rR",
    "twoFactorCode": SteamTotp.getAuthCode("C/1eXPTuPVQuOTRUwgHCXck6rp4=")
};

client.logOn(logOnOptions);

client.on('loggedOn', function() {
    console.log("Logged into Steam");
});

client.on('webSession', function(sessionID, cookies) {
    community.setCookies(cookies, (err) => {
        if(err){
            logger.error(`| COMMUNITY | ERROR: ${err.toString()}`);
        }else{
            logger.correct(`| COMMUNITY | Ready for receive offer!`);
        }
    });
    manager.setCookies(cookies, (err) => {
        if (err) {
            logger.error(`| WEB COOKIES | ERROR: ${err.message} | ${err.toString()}`);
        } else {
            logger.correct(`| WEB COOKIES | Ready for receive offer!`);
        }
        console.log("Cookies set");
    });
    updateInventory().catch(console.error);

    community.startConfirmationChecker(10000, 'sZ8x6A4TGfgfQl32q0ce14aAFPk=');
});

async function updateInventory() {
    const inventory = await new Promise((resolve, reject) => {
        manager.getInventoryContents(252490, 2, true, (err, inv) => {
            if (err) {
                logger.error(`| INVENTORY | ${err}`);
                reject(err);
            } else {
                resolve(inv);
            }
        });
    });
    for (const item in inventory) {
        console.log(item);
        console.log(item.assetid);
        console.log(item.name);
    }
}

manager.on('sentOfferChanged', function(offer, oldState) {
    console.log(`Offer #${offer.id} changed: ${TradeOfferManager.ETradeOfferState[oldState]} -> ${TradeOfferManager.ETradeOfferState[offer.state]}`);
});

manager.on('newOffer', async (offer) {
    await processTrade(offer);
    // console.log("New offer #" + offer.id + " from " + offer.partner.getSteam3RenderedID());
    // offer.accept(function(err, status) {
    //     if (err) {
    //         console.log("Unable to accept offer: " + err.message);
    //     } else {
    //         console.log("Offer accepted: " + status);
    //         if (status == "pending") {
    //             community.acceptConfirmationForObject("identitySecret", offer.id, function(err) {
    //                 if (err) {
    //                     console.log("Can't confirm trade offer: " + err.message);
    //                 } else {
    //                     console.log("Trade offer " + offer.id + " confirmed");
    //                 }
    //             });
    //         }
    //     }
    // });
});
const processTrade = async (offer) => {
    logger.correct(`Получили оффер`);
    if (offer.partner.getSteamID64() == "76561198256834741") {
        offer.accept(async (err, status) => {
            if (err) {
                logger.error(`| Трейд от SAM | ${err}`);
            } else {
                logger.correct("Приняли трейд от SAM.");
            }
        });
        return;
    }

    if (offer.itemsToGive.length > 0) {
        logger.error(`| НОВЫЙ ОБМЕН | ${offer.partner.getSteamID64()} Попытка украсть предметы с бота`);
        addOfferLog(offerMongo, offerStatusEnum.Declined, `${offer.partner.getSteamID64()} пытался украсть скины с бота. Трейд отклонён`);
        offerMongo.status = offerStatusEnum.StealItem;
        offer.decline(err => {
            if (err) {
                logger.error(err);
            } else {
                logger.correct(`| НОВЫЙ ОБМЕН | Отклонили трейд`);
            }
        });
        return;
    }

    for (const item in offer.itemsToReceive) {

    }
};