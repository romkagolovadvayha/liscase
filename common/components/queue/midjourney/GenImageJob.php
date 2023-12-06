<?php

namespace common\components\queue\midjourney;

use common\components\google\TranslateApi;
use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\blog\BlogImage;
use common\models\settings\Settings;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class GenImageJob extends BaseObject implements JobInterface
{
    public $postId;
    public $description;
    public $key;
    public $repeat = 0;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        if ($this->repeat >= 2) {
            $post = Blog::findOne($this->postId);
            $post->content = str_replace($this->key, "", $post->content);
            $post->save();
            return;
        }
        try {
            $this->description = str_replace('&#39;', '', $this->description);
            // stop words
            $subject = Yii::t('database', Settings::getByKey('subject', false), [], 'en-US');
            $this->description = str_replace(["Blood", "Bloodbath", "Crucifixion", "Bloody", "Flesh", "Bruises", "Car crash", "Corpse", "Crucified", "Cutting", "Decapitate", "Infested", "Gruesome", "Kill (as in Kill la Kill)", "Infected", "Sadist", "Slaughter", "Teratoma", "Tryphophobia", "Wound", "Cronenberg", "Khorne", "Cannibal", "Cannibalism", "Visceral", "Guts", "Bloodshot", "Gory", "Killing", "Surgery", "Vivisection", "Massacre", "Hemoglobin", "Suicide", "Female Body Parts", "Drugs", "Cocaine", "Heroin", "Meth", "Crack", "no clothes", "Speedo", "au naturale", "no shirt", "bare chest", "nude", "barely dressed", "bra", "risqué", "clear", "scantily", "clad", "cleavage", "stripped", "full frontal unclothed", "invisible clothes", "wearing nothing", "lingerie with no shirt", "naked", "without clothes on", "negligee", "zero clothes", "ahegao", "pinup", "ballgag", "Playboy", "Bimbo", "pleasure", "bodily fluids", "pleasures", "boudoir", "rule34", "brothel", "seducing", "dominatrix", "seductive", "erotic seductive", "fuck", "sensual", "Hardcore", "sexy", "Hentai", "Shag", "horny", "shibari", "incest", "Smut", "jav", "succubus", "Jerk off king at pic", "thot", "kinbaku", "transparent", "legs spread", "twerk", "making love", "voluptuous", "naughty", "wincest", "orgy", "Sultry", "XXX", "Bondage", "Bdsm", "Dog collar", "Slavegirl", "Transparent and Translucent", "Taboo", "Fascist", "Nazi", "Prophet Mohammed", "Slave", "Coon", "Honkey", "Arrested", "Jail", "Handcuffs", "Labia", "Ass", "Mammaries", "Human centipede", "Badonkers", "Minge (Slang for vag)", "Massive chests", "Big Ass", "Mommy Milker (milker or mommy is fine)", "Booba", "Nipple", "Booty", "oppai", "Bosom", "Organs", "Breasts", "Ovaries", "Busty", "Penis", "Clunge (British slang for vagina)", "Phallus", "Crotch", "sexy female", "Dick (as in Moby-Dick)", "Skimpy", "Girth", "Thick", "Honkers", "Vagina", "Hooters", "Veiny", "Knob", "Torture", "Disturbing", "Farts, Fart", "Poop", "Warts", "Xi Jinping", "Shit", "Pleasure", "Errect", "Big Black", "Brown pudding", "Bunghole", "Vomit", "Voluptuous", "Seductive", "Sperm", "Hot", "Sexy", "Sensored", "Censored", "Silenced", "Deepfake", "Inappropriate", "Pus", "Waifu", "mp5", "Succubus", "1488", "Surgery"], '', $this->description);
            $src = Yii::$app->midjourney->getGenerateImage($this->description . " " . $subject);
            /** @var Blog $post */
            $post = Blog::findOne($this->postId);
            if (empty($src) && $this->repeat == 2) {
                $post->content = str_replace($this->key, "", $post->content);
                $post->save();
                return;
            }
            $src = $this->_load($src, $post, $this->key);
            $post->content = str_replace($this->key, "<img src=\"$src\" alt=\"$this->description\">", $post->content);
            $post->save();

            $image = new BlogImage();
            $image->link = $src;
            $image->blog_id = $this->postId;
            $image->description = $this->description;
            $image->save();
        } catch (\Exception $e) {
            $post = Blog::findOne($this->postId);
            if ($this->repeat < 2) {
                $this->repeat++;
                $google = new TranslateApi();
                $imageDescEng = $google->translateText($post->name, 'en');
                $subject = Yii::t('database', Settings::getByKey('subject', false), [], 'en-US');
                Yii::$app->queueMidjourney->push(new GenImageJob([
                    'postId'      => $this->postId,
                    'description' => $imageDescEng . " " . $subject . ".",
                    'key'         => $this->key,
                    'repeat'         => $this->repeat,
                ]));
            }
        }
    }

    /**
     * @param $imageUrl
     * @param Blog $post
     *
     * @return string
     */
    private function _load($imageUrl, $post, $key) {
        $uploadDir = Yii::getAlias('@frontend/web');
        $md5 = md5($key);
        $fileUrl = "/uploads/posts/{$post->link_name}_$md5.png";
        $filePath = $uploadDir . $fileUrl;
        if (file_exists($filePath)) {
            return $fileUrl;
        }
        if (!file_exists(dirname(dirname($filePath)))) {
            mkdir(dirname(dirname($filePath)));
            chmod(dirname(dirname($filePath)), 0777);
        }
        file_put_contents($filePath, file_get_contents($imageUrl));
        return $fileUrl;
    }

}