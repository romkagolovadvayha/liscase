<?php

namespace common\components\queue\process;

use common\models\map\Map;
use common\models\map\MapList;
use common\models\servers\Servers;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\queue\JobInterface;

class CustomMapGenerateJob extends BaseObject implements JobInterface
{
    public $size;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            $config = $this->getSearchBanditQuery($this->size);
            if (rand(1, 2) == 2) {
                $config = $this->getSearchOutpostQuery($this->size);
            }

            $response = (clone \Yii::$app->curl)
                ->setHeader('X-API-Key', '03f6a4103d7d4820bed03f4322f72f26')
                ->setHeader('x-org-id', '80768c5712f64555bab1e2cae7441429')
                ->setHeader('Content-Type', 'application/json')
                ->setRawPostData($config)
                ->post('https://api.rustmaps.com/v4/maps/custom');

            $response = json_decode($response, 1);
print_r($response);
            if (!empty($response['data']) && !empty($response['data']['mapId'])) {
                $model = new MapList();
                $model->hash = $response['data']['mapId'];
                $model->size = $this->size;
                $model->save(false);
            } else {
                sleep(60 * 10);
                \Yii::$app->queueProcess->push(new CustomMapGenerateJob(['size'  => $this->size]));
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("CustomMapGenerateJob: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }

    public function getSearchOutpostQuery($size) {
        return '{
  "mapParameters": {
    "size": ' . $size . ',
    "seed": 111,
    "staging": true
  },
  "customMapSettings": {
	  "generateRingRoad": "Wanted",
	  "generateAboveGroundTrainTracks": "Wanted",
	  "removeSmallPowerLines": false,
	  "removeLargePowerLines": false,
	  "removeCarWrecks": false,
	  "removeRivers": false,
	  "allowBuildingOnRoads": false,
	  "modifyTiers": false,
	  "trySpawningOutpostInCenter": false,
	  "terrainConfiguration": {
		"islandConfig": {
		  "enabled": true,
		  "intensity": 4
		},
		"mountainConfig": {
		  "reduceMountains": false
		},
		"tierConfig": {
		  "enabled": false,
		  "tier0Percentage": 0.3,
		  "tier1Percentage": 0.3,
		  "tier2Percentage": 0.4
		},
		"biomeConfig": {
		  "enabled": true,
		  "aridPercentage": 0.5,
		  "temperatePercentage": 0.08,
		  "tundraPercentage": 0.1,
		  "arcticPercentage": 0.32,
		  "junglePercentage": 0.5
		},
		"flattenShoreAndBay": false,
		"biomeAxisAngle": "Default",
		"lootAxisAngle": "Default"
	  },
	  "oilRigConfigurations": [
		{
		  "biomePreference": {
			"enabled": false,
			"biome": "Snow"
		  },
		  "position": {
			"enabled": false,
			"alignment": "Left",
			"position": 0.13953489
		  },
		  "desired": true,
		  "type": "Large Oilrig",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "biomePreference": {
			"enabled": false,
			"biome": "Desert"
		  },
		  "position": {
			"enabled": false,
			"alignment": "Bottom",
			"position": 0.91085273
		  },
		  "desired": true,
		  "type": "Small Oilrig",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "safezones": [
		{
		  "customPrefab": {
			"enabled": true,
			"id": "ba8bcb8c8e2f4be9927ca4bac3e97c1c"
		  },
		  "desired": true,
		  "type": "Outpost",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "customPrefab": {
			"enabled": false,
			"id": "default"
		  },
		  "desired": false,
		  "type": "Bandit Town",
		  "blocked": true,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "customPrefab": {
			"enabled": false,
			"id": "default"
		  },
		  "desired": true,
		  "type": "Fishing Village A",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "customPrefab": {
			"enabled": false,
			"id": "default"
		  },
		  "desired": true,
		  "type": "Fishing Village B",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "customPrefab": {
			"enabled": false,
			"id": "default"
		  },
		  "desired": true,
		  "type": "Fishing Village C",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "customPrefab": {
			"enabled": false,
			"id": "default"
		  },
		  "desired": true,
		  "type": "Ranch",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "customPrefab": {
			"enabled": false,
			"id": "default"
		  },
		  "desired": true,
		  "type": "Large Barn",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		}
	  ],
	  "largeMonuments": [
		{
		  "desired": true,
		  "type": "Airfield",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Junkyard",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Launch Site",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Military Tunnels",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Powerplant",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Trainyard",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Water Treatment",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Sphere Tank",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Satellite Dish",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Nuclear Missile Silo",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": false,
		  "type": "Radtown",
		  "blocked": true,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": false,
		  "type": "Ziggurat",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Excavator",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "desired": true,
		  "type": "Large Oilrig",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "desired": true,
		  "type": "Small Oilrig",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "smallMonuments": [
		{
		  "type": "Gas Station",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Supermarket",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Warehouse",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Lighthouse",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Sewer Branch",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Swamp A",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Swamp B",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Swamp C",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Military Base A",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Military Base B",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Military Base C",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Military Base D",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Arctic Research Base A",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "harbors": [
		{
		  "type": "Small Harbor",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Large Harbor",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Ferry Terminal",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		}
	  ],
	  "waterWells": [
		{
		  "type": "Water Well A",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Water Well B",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Water Well C",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Water Well D",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Water Well E",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "caves": [
		{
		  "type": "Cave Large Hard",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Large Medium",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Large Sewers Hard",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Medium Easy",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Medium Hard",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Medium Medium",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Small Easy",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Small Hard",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Small Medium",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "mountains": [
		{
		  "type": "Mountain 1",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Mountain 2",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Mountain 3",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Mountain 4",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Mountain 5",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "quarries": [
		{
		  "type": "Sulfur Quarry",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Stone Quarry",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Hqm Quarry",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		}
	  ],
	  "icebergs": [
		{
		  "type": "Iceberg 1",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Iceberg 2",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Iceberg 3",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Iceberg 4",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Iceberg 5",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "iceLakes": [
		{
		  "type": "Ice Lake 1",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Ice Lake 2",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Ice Lake 3",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Ice Lake 4",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "ruins": [
		{
		  "type": "Ruin A",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Ruin B",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Ruin C",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Ruin D",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Ruin E",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "fileName": {
		"0": "{Custom=\'prostoj\'}",
		"2": "{_}",
		"3": "{Size}",
		"4": "{_}",
		"5": "{Seed}",
		"6": "{_}",
		"7": "{Date}"
	  },
	  "webhook": {
		"enabled": true,
		"url": "https://api.prostoj.store/map/webhook"
	  },
	  "underwaterLabsConfiguration": {
		"minAmount": 1,
		"maxAmount": 3,
		"blocked": false,
		"generate": "Wanted"
	  },
	  "lakesConfiguration": {
		"minAmount": 1,
		"maxAmount": 2,
		"blocked": false,
		"generate": "Wanted"
	  },
	  "oasesConfiguration": {
		"minAmount": 1,
		"maxAmount": 2,
		"blocked": false,
		"generate": "Wanted"
	  },
	  "canyonsConfiguration": {
		"minAmount": 1,
		"maxAmount": 2,
		"blocked": false,
		"generate": "Wanted"
	  },
	  "blockedPrefabs": [],
	  "removeUndergroundTunnels": false,
	  "embedCargoShipPath": false
	}
}';
    }

    public function getSearchBanditQuery($size) {
        return '{
  "mapParameters": {
    "size": ' . $size . ',
    "seed": 111,
    "staging": true
  }, "customMapSettings": {
	  "generateRingRoad": "Wanted",
	  "generateAboveGroundTrainTracks": "Wanted",
	  "removeSmallPowerLines": false,
	  "removeLargePowerLines": false,
	  "removeCarWrecks": false,
	  "removeRivers": false,
	  "allowBuildingOnRoads": false,
	  "modifyTiers": false,
	  "trySpawningOutpostInCenter": false,
	  "terrainConfiguration": {
		"islandConfig": {
		  "enabled": true,
		  "intensity": 4
		},
		"mountainConfig": {
		  "reduceMountains": false
		},
		"tierConfig": {
		  "enabled": false,
		  "tier0Percentage": 0.3,
		  "tier1Percentage": 0.3,
		  "tier2Percentage": 0.4
		},
		"biomeConfig": {
		  "enabled": true,
		  "aridPercentage": 0.5,
		  "temperatePercentage": 0.08,
		  "tundraPercentage": 0.1,
		  "arcticPercentage": 0.32,
		  "junglePercentage": 0.5
		},
		"flattenShoreAndBay": false,
		"biomeAxisAngle": "Default",
		"lootAxisAngle": "Default"
	  },
	  "oilRigConfigurations": [
		{
		  "biomePreference": {
			"enabled": false,
			"biome": "Snow"
		  },
		  "position": {
			"enabled": false,
			"alignment": "Left",
			"position": 0.13953489
		  },
		  "desired": true,
		  "type": "Large Oilrig",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "biomePreference": {
			"enabled": false,
			"biome": "Desert"
		  },
		  "position": {
			"enabled": false,
			"alignment": "Bottom",
			"position": 0.91085273
		  },
		  "desired": true,
		  "type": "Small Oilrig",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "safezones": [
		{
		  "customPrefab": {
			"enabled": false,
			"id": "ba8bcb8c8e2f4be9927ca4bac3e97c1c"
		  },
		  "desired": true,
		  "type": "Outpost",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "customPrefab": {
			"enabled": false,
			"id": "default"
		  },
		  "desired": true,
		  "type": "Bandit Town",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "customPrefab": {
			"enabled": false,
			"id": "default"
		  },
		  "desired": true,
		  "type": "Fishing Village A",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "customPrefab": {
			"enabled": false,
			"id": "default"
		  },
		  "desired": true,
		  "type": "Fishing Village B",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "customPrefab": {
			"enabled": false,
			"id": "default"
		  },
		  "desired": true,
		  "type": "Fishing Village C",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "customPrefab": {
			"enabled": false,
			"id": "default"
		  },
		  "desired": true,
		  "type": "Ranch",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "customPrefab": {
			"enabled": false,
			"id": "default"
		  },
		  "desired": true,
		  "type": "Large Barn",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		}
	  ],
	  "largeMonuments": [
		{
		  "desired": true,
		  "type": "Airfield",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Junkyard",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Launch Site",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Military Tunnels",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Powerplant",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Trainyard",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Water Treatment",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Sphere Tank",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Satellite Dish",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Nuclear Missile Silo",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": false,
		  "type": "Radtown",
		  "blocked": true,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": false,
		  "type": "Ziggurat",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "desired": true,
		  "type": "Excavator",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "desired": true,
		  "type": "Large Oilrig",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "desired": true,
		  "type": "Small Oilrig",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "smallMonuments": [
		{
		  "type": "Gas Station",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Supermarket",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Warehouse",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Lighthouse",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Sewer Branch",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Swamp A",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Swamp B",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Swamp C",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Military Base A",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Military Base B",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Military Base C",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Military Base D",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Arctic Research Base A",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "harbors": [
		{
		  "type": "Small Harbor",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Large Harbor",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Ferry Terminal",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		}
	  ],
	  "waterWells": [
		{
		  "type": "Water Well A",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Water Well B",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Water Well C",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Water Well D",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Water Well E",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "caves": [
		{
		  "type": "Cave Large Hard",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Large Medium",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Large Sewers Hard",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Medium Easy",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Medium Hard",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Medium Medium",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Small Easy",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Small Hard",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Cave Small Medium",
		  "blocked": true,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "mountains": [
		{
		  "type": "Mountain 1",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Mountain 2",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Mountain 3",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Mountain 4",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Mountain 5",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "quarries": [
		{
		  "type": "Sulfur Quarry",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Stone Quarry",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		},
		{
		  "type": "Hqm Quarry",
		  "blocked": false,
		  "allowedToSetBiomes": true,
		  "biomePreferences": [
			{
			  "biomeType": "Snow",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Forest",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Tundra",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Desert",
			  "selection": "NoPreference"
			},
			{
			  "biomeType": "Jungle",
			  "selection": "NoPreference"
			}
		  ]
		}
	  ],
	  "icebergs": [
		{
		  "type": "Iceberg 1",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Iceberg 2",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Iceberg 3",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Iceberg 4",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Iceberg 5",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "iceLakes": [
		{
		  "type": "Ice Lake 1",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Ice Lake 2",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Ice Lake 3",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Ice Lake 4",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "ruins": [
		{
		  "type": "Ruin A",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Ruin B",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Ruin C",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Ruin D",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		},
		{
		  "type": "Ruin E",
		  "blocked": false,
		  "allowedToSetBiomes": false,
		  "biomePreferences": null
		}
	  ],
	  "fileName": {
		"0": "{Custom=\'prostoj\'}",
		"2": "{_}",
		"3": "{Size}",
		"4": "{_}",
		"5": "{Seed}",
		"6": "{_}",
		"7": "{Date}"
	  },
	  "webhook": {
		"enabled": true,
		"url": "https://api.prostoj.store/map/webhook"
	  },
	  "underwaterLabsConfiguration": {
		"minAmount": 1,
		"maxAmount": 3,
		"blocked": false,
		"generate": "Wanted"
	  },
	  "lakesConfiguration": {
		"minAmount": 1,
		"maxAmount": 2,
		"blocked": false,
		"generate": "Wanted"
	  },
	  "oasesConfiguration": {
		"minAmount": 1,
		"maxAmount": 2,
		"blocked": false,
		"generate": "Wanted"
	  },
	  "canyonsConfiguration": {
		"minAmount": 1,
		"maxAmount": 2,
		"blocked": false,
		"generate": "Wanted"
	  },
	  "blockedPrefabs": [],
	  "removeUndergroundTunnels": false,
	  "embedCargoShipPath": false
	}
}
  ';
    }
}