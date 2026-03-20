namespace DemoUploadMod
{
    public static class Config
    {
        public static string UploadUrl => ModConfig.UploadUrl ?? "";
        public static string ApiKey => ModConfig.ApiKey ?? "";
        public static bool Enabled => ModConfig.Enabled;
        public static bool UseFolderWatcherFallback => ModConfig.UseFolderWatcherFallback;
    }
}
