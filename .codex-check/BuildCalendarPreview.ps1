$source = Join-Path $PSScriptRoot '..\plugins\ProstojMenu_Stats_UI.json.backup'
$target = Join-Path $PSScriptRoot 'Performance_Calendar_Current.json'
$doc = Get-Content $source -Raw -Encoding UTF8 | ConvertFrom-Json
$rootIndex = [Array]::FindIndex([object[]]$doc.Elements, [Predicate[object]]{ param($element) $element.Name -eq 'ProstojMenu.Root_1' })
$nextRootIndex = $doc.Elements.Count
for ($i = $rootIndex + 1; $i -lt $doc.Elements.Count; $i++) {
    if ($doc.Elements[$i].Parent -eq 'Overlay') { $nextRootIndex = $i; break }
}
$elements = @($doc.Elements[$rootIndex..($nextRootIndex - 1)])
$remove = @('ProstojMenu.Root.Frame.Artwork_1','ProstojMenu.Root.Frame.Shade_1','ProstojMenu.Root.Frame.BorderTop','ProstojMenu.Root.Frame.BorderWarm','ProstojMenu.Root.Frame.Sidebar.Nav.calendar.Accent')
$elements = @($elements | Where-Object { $remove -notcontains $_.Name -and $_.Name -notlike '*.Meta*' })

$colorMap = @{
 '0.012 0.008 0.035'='0.020 0.020 0.018'; '0.025 0.012 0.075'='0.259 0.247 0.220';
 '0.040 0.026 0.085'='0.259 0.247 0.220'; '0.018 0.010 0.055'='0.259 0.247 0.220';
 '0.025 0.015 0.067'='0.259 0.247 0.220'; '0.010 0.006 0.035'='0.259 0.247 0.220';
 '0.180 0.102 0.231'='0.330 0.314 0.290'; '0.05 0.035 0.10'='0.412 0.392 0.369';
 '0.018 0.012 0.055'='0.259 0.247 0.220'; '0.045 0.030 0.085'='0.412 0.392 0.369';
 '0.06 0.04 0.105'='0.412 0.392 0.369'; '0.145 0.052 0.118'='0.412 0.392 0.369';
 '0.97 0.94 0.99'='0.835 0.808 0.769'; '0.82 0.77 0.88'='0.835 0.808 0.769';
 '0.60 0.55 0.68'='0.650 0.625 0.590'; '0.47 0.42 0.56'='0.650 0.625 0.590';
 '0.98 0.95 1'='0.835 0.808 0.769'; '1 0.380 0.204'='0.520 0.495 0.455';
 '0.922 0.047 0.208'='0.860 0.220 0.330'
}
foreach($e in $elements){
 foreach($prefix in $colorMap.Keys){if($e.RustColor -and $e.RustColor.StartsWith($prefix+' ')){$e.RustColor=$colorMap[$prefix]+$e.RustColor.Substring($prefix.Length);break}}
}
function SetE($name,$min,$max,$color=$null){
    $e=$elements|Where-Object Name -eq $name|Select-Object -First 1
    if($e){
        $minParts=$min.Split(' '); $maxParts=$max.Split(' ')
        $e.AnchorMin=$min; $e.AnchorMax=$max
        $e.AnchorMinVec.x=[double]::Parse($minParts[0],[Globalization.CultureInfo]::InvariantCulture)
        $e.AnchorMinVec.y=[double]::Parse($minParts[1],[Globalization.CultureInfo]::InvariantCulture)
        $e.AnchorMaxVec.x=[double]::Parse($maxParts[0],[Globalization.CultureInfo]::InvariantCulture)
        $e.AnchorMaxVec.y=[double]::Parse($maxParts[1],[Globalization.CultureInfo]::InvariantCulture)
        if($color){$e.RustColor=$color}
    }
}
SetE 'ProstojMenu.Root_1' '0 0' '1 1' '0.020 0.020 0.018 0.62'
SetE 'ProstojMenu.Root.Frame_1' '0.095 0.09' '0.905 0.91' '0 0 0 0'
SetE 'ProstojMenu.Root.Frame.Sidebar_1' '0 0' '0.22 1' '0.259 0.247 0.220 0.92'
SetE 'ProstojMenu.Root.Frame.Main_1' '0.22 0' '1 1' '0.259 0.247 0.220 0.94'
SetE 'ProstojMenu.Root.Frame.Main.Header_1' '0 0.895' '0.998 0.998' '0.259 0.247 0.220 0.92'
SetE 'ProstojMenu.Root.Frame.Main.Content_1' '0.018 0.02' '0.982 0.875' '0 0 0 0'
$nav=@('store','calendar','stats','top');for($i=0;$i-lt$nav.Count;$i++){$max=0.875-$i*0.0695;$min=$max-0.068;$n='ProstojMenu.Root.Frame.Sidebar.Nav.'+$nav[$i]+'_1';SetE $n ('0 '+$min.ToString('0.####',[Globalization.CultureInfo]::InvariantCulture)) ('1 '+$max.ToString('0.####',[Globalization.CultureInfo]::InvariantCulture)) $(if($nav[$i]-eq'calendar'){'0.860 0.220 0.330 0.88'}else{'0 0 0 0'});foreach($label in $elements|Where-Object{$_.Parent-eq$n-and$_.Type-eq'Label'}){SetE $label.Name '0.285 0' '0.90 1';$label.FontSize=13}}
SetE 'ProstojMenu.Root.Frame.Main.Content.Calendar' '0 0.075' '1 0.89' '0.259 0.247 0.220 0.92'
SetE 'ProstojMenu.Root.Frame.Main.Content.MonthBar' '0 0.895' '1 1' '0.412 0.392 0.369 0.80'
$head=$elements|Where-Object Name -eq 'ProstojMenu.Root.Frame.Main.Content.Heading'|Select-Object -First 1;if($head){$head.Parent='ProstojMenu.Root.Frame.Main.Header_1';$head.AnchorMin='0.025 0.18';$head.AnchorMax='0.46 0.84';$head.FontSize=21;$head.LabelText='КАЛЕНДАРЬ'}
foreach($n in @('ProstojMenu.Root.Frame.Main.Content.Eyebrow','ProstojMenu.Root.Frame.Main.Content.Subheading')){$e=$elements|Where-Object Name -eq $n|Select-Object -First 1;if($e){$e.ActiveSelf=$false}}
$out=[ordered]@{Version=$doc.Version;Elements=$elements;LangEntries=$doc.LangEntries;LangLanguages=$doc.LangLanguages;ImageLib=$doc.ImageLib;Templates=$doc.Templates;PluginSettings=$doc.PluginSettings}
[IO.File]::WriteAllText($target,($out|ConvertTo-Json -Depth 20),[Text.UTF8Encoding]::new($false))
Write-Output $target
