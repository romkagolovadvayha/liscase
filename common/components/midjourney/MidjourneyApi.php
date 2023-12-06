<?php

namespace common\components\midjourney;

use Ferranfg\MidjourneyPhp\Midjourney;
use Yii;

class MidjourneyApi
{
    public $discordChannelId;
    public $discordUserToken;

    /**
     * @param $promptText
     *
     * @return mixed
     */
    public function getGenerateImage($promptText)
    {
        $midjourney = new MidjourneyImageCreator($this->discordChannelId, $this->discordUserToken);
//        $promptText .= "Use a Sony α7 III camera with a 85mm lens at F 1.2 aperture setting to blur the background and isolate the subject. Use dreamlike lighting with soft sunlight falling on the subject’s face and hair. The image should be shot in high resolution and in a 9:16 aspect ratio. credits to tipseason.com. Use the Midjourney v5 with photorealism mode turned on to create an ultra-realistic image that captures the subject’s natural beauty and personality.";
        //--tile бесшевный режим
        //--iw (0-2) 2 - будет брать за основу изображение, 0 - за основу текст
        $promptTags = "realistic --q .75 --v 5.1 --style raw --stylize 450";

        /**
         * The imageCreationV2 method is responsible for randomly selecting an image from the 4 options provided by Midjourney.
         * If you want to specify a particular image, you can pass its identifier (ranging from 0 to 3) as the third parameter.
         *
         * Example: $midjourneyImageCreator->imageCreation($promptText, $promptTags, 0);
         *
         * This will generate an image for the given prompt, using the specified image identifier (in this case, 0).
         */
        $message = $midjourney->imageCreationV2($promptText, $promptTags);
        if (empty($message)) {
            return null;
        }

        return $message->upscaled_photo_url;
    }

}