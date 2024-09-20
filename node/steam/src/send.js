const SteamUser = require('steam-user');
const SteamCommunity = require('steamcommunity');
const SteamTotp = require('steam-totp');
const TradeOfferManager = require('steam-tradeoffer-manager/lib/index.js'); // use require('steam-tradeoffer-manager') in production
const FS = require('fs');

let client = new SteamUser();
let manager = new TradeOfferManager({
    "steam": client, // Polling every 30 seconds is fine since we get notifications from Steam
    "domain": "prostoj.store", // Our domain is example.com
    "language": "ru" // We want English item descriptions
});
let community = new SteamCommunity();

// Steam logon options
let logOnOptions = {
    "accountName": "",
    "password": "",
    "twoFactorCode": SteamTotp.getAuthCode("sharedSecret")
};
// Steam logon options
// let logOnOptions = {
//     "accountName": "romkadvayha",
//     "password": "2580422rR",
//     "twoFactorCode": SteamTotp.getAuthCode("sharedSecret")
// };

if (FS.existsSync('polldata.json')) {
    manager.pollData = JSON.parse(FS.readFileSync('polldata.json').toString('utf8'));
}

client.logOn(logOnOptions);

client.on('loggedOn', function() {
    console.log("Logged into Steam");
});

client.on('webSession', function(sessionID, cookies) {
    manager.setCookies(cookies, function(err) {
        if (err) {
            console.log(err);
            process.exit(1); // Fatal error
            return;
        }

        console.log("Cookies set");

        // Get our inventory
        manager.getInventoryContents(252490, 2, true, function(err, inventory) {
            if (err) {
                console.log(err);
                return;
            }

            if (inventory.length == 0) {
                // Inventory empty
                console.log("CS:GO inventory is empty");
                return;
            }

            console.log("Found " + inventory.length + " CS:GO items");

            // Create and send the offer
            // let offer = manager.createOffer("https://steamcommunity.com/tradeoffer/new/?partner=12345678&token=xxxxxxxx");
            // offer.addMyItems(inventory);
            // offer.setMessage("Here, have some items!");
            // offer.send(function(err, status) {
            //     if (err) {
            //         console.log(err);
            //         return;
            //     }
            //
            //     if (status == 'pending') {
            //         // We need to confirm it
            //         console.log(`Offer #${offer.id} sent, but requires confirmation`);
            //         community.acceptConfirmationForObject("identitySecret", offer.id, function(err) {
            //             if (err) {
            //                 console.log(err);
            //             } else {
            //                 console.log("Offer confirmed");
            //             }
            //         });
            //     } else {
            //         console.log(`Offer #${offer.id} sent successfully`);
            //     }
            // });
        });
    });

    community.setCookies(cookies);
});

manager.on('sentOfferChanged', function(offer, oldState) {
    console.log(`Offer #${offer.id} changed: ${TradeOfferManager.ETradeOfferState[oldState]} -> ${TradeOfferManager.ETradeOfferState[offer.state]}`);
});

manager.on('pollData', function(pollData) {
    FS.writeFileSync('polldata.json', JSON.stringify(pollData));
});

manager.on('newOffer', function(offer) {
    console.log("New offer #" + offer.id + " from " + offer.partner.getSteam3RenderedID());
    offer.accept(function(err, status) {
        if (err) {
            console.log("Unable to accept offer: " + err.message);
        } else {
            console.log("Offer accepted: " + status);
            if (status == "pending") {
                community.acceptConfirmationForObject("identitySecret", offer.id, function(err) {
                    if (err) {
                        console.log("Can't confirm trade offer: " + err.message);
                    } else {
                        console.log("Trade offer " + offer.id + " confirmed");
                    }
                });
            }
        }
    });
});