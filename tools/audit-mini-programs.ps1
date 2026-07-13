[CmdletBinding()]
param(
  [string]$OutputRoot = "docs/generated"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$workspace = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$outputPath = Join-Path $workspace $OutputRoot
$apps = @("会员端", "管理端")
$textExtensions = @(".js", ".json", ".wxml", ".wxss", ".wxs")
$pageRoots = @(
  "pages", "pageHome", "pageCourse", "pageMine", "pagesCourse",
  "pagesImp", "pageMember", "pageReport", "pageServer", "pageChain",
  "pageConfig", "uview-ui"
)

New-Item -ItemType Directory -Force -Path $outputPath | Out-Null

function Get-RelativePath {
  param(
    [Parameter(Mandatory = $true)][string]$BasePath,
    [Parameter(Mandatory = $true)][string]$TargetPath
  )

  return [IO.Path]::GetRelativePath($BasePath, $TargetPath).Replace("\", "/")
}

function Get-FileCategory {
  param([Parameter(Mandatory = $true)][string]$RelativePath)

  if ($RelativePath -match '(^|/)(node-modules|uview-ui|@babel)(/|$)') {
    return "third_party"
  }
  if ($RelativePath -match '(^|/)\.DS_Store$') {
    return "metadata"
  }
  if ([IO.Path]::GetExtension($RelativePath).ToLowerInvariant() -in @(".png", ".jpg", ".jpeg", ".gif", ".webp", ".svg", ".mp4")) {
    return "asset"
  }
  if ($RelativePath -match '^common/(runtime|vendor)\.js$') {
    return "compiled_runtime"
  }
  return "first_party_compiled"
}

function Resolve-MiniProgramReference {
  param(
    [Parameter(Mandatory = $true)][string]$AppRoot,
    [Parameter(Mandatory = $true)][string]$SourceFile,
    [Parameter(Mandatory = $true)][string]$Reference
  )

  $clean = ($Reference -split '[?#]')[0]
  if (-not $clean -or $clean -match '^(https?:|data:|plugin:|ext:)') {
    return $null
  }

  if ($clean.StartsWith('@/')) {
    $candidate = Join-Path $AppRoot $clean.Substring(2)
  }
  elseif ($clean.StartsWith('/')) {
    $candidate = Join-Path $AppRoot $clean.TrimStart('/')
  }
  else {
    $candidate = Join-Path (Split-Path $SourceFile -Parent) $clean
  }

  return [IO.Path]::GetFullPath($candidate)
}

$inventory = [Collections.Generic.List[object]]::new()
$pages = [Collections.Generic.List[object]]::new()
$packages = [Collections.Generic.List[object]]::new()
$components = [Collections.Generic.List[object]]::new()
$apis = [Collections.Generic.List[object]]::new()
$apiUsages = [Collections.Generic.List[object]]::new()
$eventHandlers = [Collections.Generic.List[object]]::new()
$navigation = [Collections.Generic.List[object]]::new()
$assets = [Collections.Generic.List[object]]::new()
$issues = [Collections.Generic.List[object]]::new()
$summaries = [Collections.Generic.List[object]]::new()

foreach ($app in $apps) {
  $appRoot = Join-Path $workspace $app
  if (-not (Test-Path -LiteralPath $appRoot -PathType Container)) {
    throw "Missing app directory: $appRoot"
  }

  $files = @(Get-ChildItem -LiteralPath $appRoot -Recurse -File -Force | Sort-Object FullName)
  $textCache = @{}
  $jsonErrors = 0
  $textReadErrors = 0

  foreach ($file in $files) {
    $relative = Get-RelativePath -BasePath $appRoot -TargetPath $file.FullName
    $extension = $file.Extension.ToLowerInvariant()
    $lineCount = $null
    $readStatus = "binary"

    if ($extension -in $textExtensions) {
      try {
        $rawContent = Get-Content -Raw -LiteralPath $file.FullName
        $content = if ($null -eq $rawContent) { "" } else { [string]$rawContent }
        $textCache[$file.FullName] = $content
        $lineCount = if ($content.Length -eq 0) { 0 } else { ([regex]::Matches($content, "`r`n|`n|`r").Count + 1) }
        $readStatus = "ok"
      }
      catch {
        $textReadErrors++
        $readStatus = "error"
        $issues.Add([pscustomobject]@{
          App = $app
          Severity = "error"
          Type = "text_read_error"
          Source = $relative
          Detail = $_.Exception.Message
        })
      }
    }

    $inventory.Add([pscustomobject]@{
      App = $app
      Path = $relative
      Extension = $extension
      Bytes = $file.Length
      Lines = $lineCount
      Sha256 = (Get-FileHash -Algorithm SHA256 -LiteralPath $file.FullName).Hash.ToLowerInvariant()
      Category = Get-FileCategory -RelativePath $relative
      ReadStatus = $readStatus
    })
  }

  $appJsonPath = Join-Path $appRoot "app.json"
  $appConfig = $textCache[$appJsonPath] | ConvertFrom-Json
  $registeredPages = [Collections.Generic.List[string]]::new()

  foreach ($page in @($appConfig.pages)) {
    $registeredPages.Add([string]$page)
    $missing = [Collections.Generic.List[string]]::new()
    foreach ($extension in @(".js", ".json", ".wxml", ".wxss")) {
      if (-not (Test-Path -LiteralPath (Join-Path $appRoot ($page + $extension)) -PathType Leaf)) {
        $missing.Add($extension)
      }
    }
    $pages.Add([pscustomobject]@{
      App = $app
      Package = "main"
      Path = $page
      MissingFiles = ($missing -join ",")
      Complete = ($missing.Count -eq 0)
    })
  }

  $subPackageRoots = [Collections.Generic.List[string]]::new()
  foreach ($subPackage in @($appConfig.subPackages)) {
    $root = ([string]$subPackage.root).TrimEnd('/')
    $subPackageRoots.Add($root)
    foreach ($page in @($subPackage.pages)) {
      $fullPage = "$root/$page"
      $registeredPages.Add($fullPage)
      $missing = [Collections.Generic.List[string]]::new()
      foreach ($extension in @(".js", ".json", ".wxml", ".wxss")) {
        if (-not (Test-Path -LiteralPath (Join-Path $appRoot ($fullPage + $extension)) -PathType Leaf)) {
          $missing.Add($extension)
        }
      }
      $pages.Add([pscustomobject]@{
        App = $app
        Package = $root
        Path = $fullPage
        MissingFiles = ($missing -join ",")
        Complete = ($missing.Count -eq 0)
      })
    }
  }

  foreach ($subRoot in $subPackageRoots) {
    $prefix = "$subRoot/"
    $bytes = ($inventory | Where-Object { $_.App -eq $app -and $_.Path.StartsWith($prefix) } | Measure-Object Bytes -Sum).Sum
    $packages.Add([pscustomobject]@{
      App = $app
      Package = $subRoot
      Bytes = [long]$bytes
      ApproxMiB = [Math]::Round(([double]$bytes / 1MB), 3)
      Kind = "subpackage"
    })
  }

  $mainBytes = 0L
  foreach ($item in @($inventory | Where-Object App -eq $app)) {
    $isSubPackage = $false
    foreach ($subRoot in $subPackageRoots) {
      if ($item.Path.StartsWith("$subRoot/")) {
        $isSubPackage = $true
        break
      }
    }
    if (-not $isSubPackage) {
      $mainBytes += [long]$item.Bytes
    }
  }
  $packages.Add([pscustomobject]@{
    App = $app
    Package = "main"
    Bytes = $mainBytes
    ApproxMiB = [Math]::Round(([double]$mainBytes / 1MB), 3)
    Kind = "main"
  })

  foreach ($jsonFile in @($files | Where-Object Extension -eq ".json")) {
    $relativeJson = Get-RelativePath -BasePath $appRoot -TargetPath $jsonFile.FullName
    try {
      $json = $textCache[$jsonFile.FullName] | ConvertFrom-Json -ErrorAction Stop
    }
    catch {
      $jsonErrors++
      $issues.Add([pscustomobject]@{
        App = $app
        Severity = "error"
        Type = "invalid_json"
        Source = $relativeJson
        Detail = $_.Exception.Message
      })
      continue
    }

    $usingComponentsProperty = $json.PSObject.Properties["usingComponents"]
    if ($null -eq $usingComponentsProperty) {
      continue
    }

    foreach ($property in @($usingComponentsProperty.Value.PSObject.Properties)) {
      $reference = [string]$property.Value
      $resolvedBase = Resolve-MiniProgramReference -AppRoot $appRoot -SourceFile $jsonFile.FullName -Reference $reference
      $exists = $true
      $resolvedRelative = "external"
      if ($null -ne $resolvedBase) {
        $resolvedRelative = Get-RelativePath -BasePath $appRoot -TargetPath $resolvedBase
        $exists = (Test-Path -LiteralPath ($resolvedBase + ".js") -PathType Leaf) -or
          (Test-Path -LiteralPath ($resolvedBase + ".json") -PathType Leaf) -or
          (Test-Path -LiteralPath $resolvedBase -PathType Leaf)
      }

      $components.Add([pscustomobject]@{
        App = $app
        Source = $relativeJson
        Name = $property.Name
        Reference = $reference
        Resolved = $resolvedRelative
        Exists = $exists
      })

      if (-not $exists) {
        $issues.Add([pscustomobject]@{
          App = $app
          Severity = "error"
          Type = "missing_component"
          Source = $relativeJson
          Detail = "$($property.Name) -> $reference"
        })
      }
    }
  }

  $registeredSet = @{}
  foreach ($page in $registeredPages) {
    $registeredSet[$page.TrimStart('/')] = $true
  }

  $pageRootPattern = ($pageRoots | ForEach-Object { [regex]::Escape($_) }) -join '|'
  $navigationPattern = '(?:url|path)\s*:\s*["''](?<target>/(?:' + $pageRootPattern + ')[^"''?#]*)'
  $assetPattern = '(?<![A-Za-z0-9_])(?<ref>(?:@/|/|\.\.?/|(?:static|imgs)/)[^"''()\s]+\.(?:png|jpg|jpeg|gif|webp|svg|mp4))'
  $runtimeAssetPattern = 'imgsrc\(\s*["''](?<ref>[^"'']+\.(?:png|jpg|jpeg|gif|webp|svg|mp4))["'']\s*\)'

  foreach ($sourceFile in @($files | Where-Object { $_.Extension.ToLowerInvariant() -in @(".js", ".json", ".wxml", ".wxss") })) {
    if (-not $textCache.ContainsKey($sourceFile.FullName)) {
      continue
    }
    $content = $textCache[$sourceFile.FullName]
    $relativeSource = Get-RelativePath -BasePath $appRoot -TargetPath $sourceFile.FullName

    if ($sourceFile.Extension -eq ".js") {
      foreach ($match in [regex]::Matches($content, $navigationPattern)) {
        $target = $match.Groups["target"].Value.TrimStart('/')
        $contextStart = [Math]::Max(0, $match.Index - 260)
        $contextLength = [Math]::Min(620, $content.Length - $contextStart)
        $context = $content.Substring($contextStart, $contextLength)
        $kind = if ($context -match 'navigateToMiniProgram\s*\(') {
          "external_mini_program"
        }
        elseif ($target -eq "pagesImp/login/bind" -and $context -match 'window\.location') {
          "cross_platform_dead_code"
        }
        else {
          "internal"
        }
        $exists = $registeredSet.ContainsKey($target) -or $kind -ne "internal"
        $line = ([regex]::Matches($content.Substring(0, $match.Index), "`n").Count + 1)
        $navigation.Add([pscustomobject]@{
          App = $app
          Source = $relativeSource
          Line = $line
          Target = $target
          Kind = $kind
          Registered = $exists
        })
        if (-not $exists) {
          $issues.Add([pscustomobject]@{
            App = $app
            Severity = "warning"
            Type = "unregistered_navigation_target"
            Source = "$relativeSource`:$line"
            Detail = $target
          })
        }
      }
    }

    $runtimeMatches = @()
    if ($sourceFile.Extension -eq ".js") {
      $runtimeMatches = @([regex]::Matches($content, $runtimeAssetPattern, [Text.RegularExpressions.RegexOptions]::IgnoreCase))
      foreach ($match in $runtimeMatches) {
        $reference = $match.Groups["ref"].Value
        $line = ([regex]::Matches($content.Substring(0, $match.Index), "`n").Count + 1)
        $assets.Add([pscustomobject]@{
          App = $app
          Source = $relativeSource
          Line = $line
          Reference = $reference
          Resolved = "common/dict.uploadURL"
          Delivery = "backend_uploadURL"
          Exists = "runtime"
        })
      }
    }

    foreach ($match in [regex]::Matches($content, $assetPattern, [Text.RegularExpressions.RegexOptions]::IgnoreCase)) {
      if ($sourceFile.Extension -eq ".js") {
        $insideRuntimeCall = $false
        foreach ($runtimeMatch in $runtimeMatches) {
          if ($match.Index -ge $runtimeMatch.Index -and $match.Index -lt ($runtimeMatch.Index + $runtimeMatch.Length)) {
            $insideRuntimeCall = $true
            break
          }
        }
        if ($insideRuntimeCall) {
          continue
        }
        $prefixStart = [Math]::Max(0, $match.Index - 10)
        if ($content.Substring($prefixStart, $match.Index - $prefixStart) -match 'https?:') {
          continue
        }
      }
      $reference = $match.Groups["ref"].Value
      if ($reference.StartsWith('//')) {
        $line = ([regex]::Matches($content.Substring(0, $match.Index), "`n").Count + 1)
        $assets.Add([pscustomobject]@{
          App = $app
          Source = $relativeSource
          Line = $line
          Reference = $reference
          Resolved = $reference
          Delivery = "remote_url"
          Exists = "runtime"
        })
        continue
      }
      $resolved = Resolve-MiniProgramReference -AppRoot $appRoot -SourceFile $sourceFile.FullName -Reference $reference
      if ($null -eq $resolved) {
        continue
      }
      $exists = Test-Path -LiteralPath $resolved -PathType Leaf
      $delivery = if ($sourceFile.Extension -eq ".js" -and -not $exists) { "backend_uploadURL" } else { "local" }
      $resolvedDisplay = if ($delivery -eq "backend_uploadURL") { "common/dict.uploadURL" } else { Get-RelativePath -BasePath $appRoot -TargetPath $resolved }
      $line = ([regex]::Matches($content.Substring(0, $match.Index), "`n").Count + 1)
      $assets.Add([pscustomobject]@{
        App = $app
        Source = $relativeSource
        Line = $line
        Reference = $reference
        Resolved = $resolvedDisplay
        Delivery = $delivery
        Exists = $exists
      })
      if (-not $exists -and $delivery -eq "local") {
        $issues.Add([pscustomobject]@{
          App = $app
          Severity = "warning"
          Type = "missing_asset"
          Source = "$relativeSource`:$line"
          Detail = $reference
        })
      }
    }
  }

  $vendorPath = Join-Path $appRoot "common/vendor.js"
  if ($textCache.ContainsKey($vendorPath)) {
    $vendor = $textCache[$vendorPath]
    $apiPattern = 't\.(?<name>[A-Za-z_$][A-Za-z0-9_$]*)\s*=\s*function\s*\([^)]*\)\s*\{\s*return\s+[\s\S]{0,180}?\.post\s*\([\s\S]{0,180}?["''](?<endpoint>/(?:b|c|common|wx)/[^"'']+)["''][\s\S]{0,280}?custom\s*:\s*\{(?<custom>[^}]*)\}'
    foreach ($match in [regex]::Matches($vendor, $apiPattern)) {
      $custom = $match.Groups["custom"].Value
      $contentTypeMatch = [regex]::Match($custom, 'contentType\s*:\s*["'']([^"'']+)["'']')
      $apis.Add([pscustomobject]@{
        App = $app
        Export = $match.Groups["name"].Value
        Method = "POST"
        Endpoint = $match.Groups["endpoint"].Value
        ContentType = if ($contentTypeMatch.Success) { $contentTypeMatch.Groups[1].Value } else { "application/json;charset=UTF-8" }
        Operation = if ($custom -match 'isWrite\s*:\s*!0') { "write" } elseif ($custom -match 'isQuery\s*:\s*!0') { "query" } else { "unspecified" }
        UseSlave = ($custom -match 'useSlave\s*:\s*!0')
        Source = "common/vendor.js"
      })
    }
  }

  foreach ($wxmlFile in @($files | Where-Object Extension -eq ".wxml")) {
    $jsPath = [IO.Path]::ChangeExtension($wxmlFile.FullName, ".js")
    if (-not $textCache.ContainsKey($jsPath)) {
      continue
    }
    $wxml = $textCache[$wxmlFile.FullName]
    $js = $textCache[$jsPath]
    $relativeWxml = Get-RelativePath -BasePath $appRoot -TargetPath $wxmlFile.FullName
    $eventPattern = "\[\s*\[\s*'\^?[^']+'\s*,\s*\[\s*\[\s*'(?<handler>[A-Za-z_`$][A-Za-z0-9_`$]*)'"
    foreach ($match in [regex]::Matches($wxml, $eventPattern)) {
      $handler = $match.Groups["handler"].Value
      if ($handler.StartsWith("__") -or $handler -eq "href") {
        continue
      }
      $handlerPattern = '(?<![A-Za-z0-9_$])' + [regex]::Escape($handler) + '\s*:|\.' + [regex]::Escape($handler) + '\s*='
      $exists = [regex]::IsMatch($js, $handlerPattern)
      $line = ([regex]::Matches($wxml.Substring(0, $match.Index), "`n").Count + 1)
      $eventHandlers.Add([pscustomobject]@{
        App = $app
        Source = $relativeWxml
        Line = $line
        Handler = $handler
        Exists = $exists
      })
      if (-not $exists) {
        $issues.Add([pscustomobject]@{
          App = $app
          Severity = "warning"
          Type = "missing_event_handler"
          Source = "$relativeWxml`:$line"
          Detail = $handler
        })
      }
    }
  }

  $apiDefinitions = @($apis | Where-Object { $_.App -eq $app -and $_.Export -ne "default" })
  $apiNames = @($apiDefinitions.Export | Sort-Object -Unique)
  if ($apiNames.Count -gt 0) {
    $apiNamePattern = ($apiNames | Sort-Object Length -Descending | ForEach-Object { [regex]::Escape($_) }) -join '|'
    $apiUsagePattern = '\(\s*0\s*,\s*[A-Za-z_$][A-Za-z0-9_$]*\.(?<name>' + $apiNamePattern + ')\s*\)\s*\('
    foreach ($sourceFile in @($files | Where-Object Extension -eq ".js")) {
      $relativeSource = Get-RelativePath -BasePath $appRoot -TargetPath $sourceFile.FullName
      if ($relativeSource -match '(^|/)(common/(vendor|runtime)\.js|node-modules/|uview-ui/|@babel/)') {
        continue
      }
      $content = $textCache[$sourceFile.FullName]
      foreach ($match in [regex]::Matches($content, $apiUsagePattern)) {
        $name = $match.Groups["name"].Value
        $definitions = @($apiDefinitions | Where-Object Export -eq $name)
        $previewStart = $match.Index + $match.Length
        $previewLength = [Math]::Min(260, $content.Length - $previewStart)
        $preview = if ($previewLength -gt 0) {
          ($content.Substring($previewStart, $previewLength) -replace '\s+', ' ').Trim()
        }
        else {
          ""
        }
        $line = ([regex]::Matches($content.Substring(0, $match.Index), "`n").Count + 1)
        $apiUsages.Add([pscustomobject]@{
          App = $app
          Export = $name
          EndpointCandidates = (($definitions.Endpoint | Sort-Object -Unique) -join " | ")
          Source = $relativeSource
          Line = $line
          ArgumentPreview = $preview
        })
      }
    }
  }

  $appInventory = @($inventory | Where-Object App -eq $app)
  $appPages = @($pages | Where-Object App -eq $app)
  $appApis = @($apis | Where-Object App -eq $app)
  $summaries.Add([pscustomobject]@{
    App = $app
    Files = $appInventory.Count
    Bytes = [long](($appInventory | Measure-Object Bytes -Sum).Sum)
    TextFiles = @($appInventory | Where-Object ReadStatus -eq "ok").Count
    TextReadErrors = $textReadErrors
    JsonErrors = $jsonErrors
    RegisteredPages = $appPages.Count
    IncompletePages = @($appPages | Where-Object Complete -eq $false).Count
    ComponentReferences = @($components | Where-Object App -eq $app).Count
    MissingComponents = @($components | Where-Object { $_.App -eq $app -and -not $_.Exists }).Count
    ApiExports = $appApis.Count
    UniqueEndpoints = @($appApis.Endpoint | Sort-Object -Unique).Count
    ApiUsageSites = @($apiUsages | Where-Object App -eq $app).Count
    EventBindings = @($eventHandlers | Where-Object App -eq $app).Count
    MissingEventHandlers = @($eventHandlers | Where-Object { $_.App -eq $app -and -not $_.Exists }).Count
    MissingNavigationTargets = @($navigation | Where-Object { $_.App -eq $app -and -not $_.Registered }).Count
    MissingAssetReferences = @($assets | Where-Object { $_.App -eq $app -and $_.Delivery -eq "local" -and -not $_.Exists }).Count
    BackendAssetReferences = @($assets | Where-Object { $_.App -eq $app -and $_.Delivery -eq "backend_uploadURL" }).Count
    RemoteUrlReferences = @($assets | Where-Object { $_.App -eq $app -and $_.Delivery -eq "remote_url" }).Count
  })
}

$inventory | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath (Join-Path $outputPath "file-inventory.csv")
$pages | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath (Join-Path $outputPath "page-inventory.csv")
$packages | Sort-Object App, Kind, Package | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath (Join-Path $outputPath "package-sizes.csv")
$components | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath (Join-Path $outputPath "component-references.csv")
$apis | Sort-Object App, Endpoint, Export -Unique | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath (Join-Path $outputPath "api-catalog.csv")
$apiUsages | Sort-Object App, Source, Line, Export | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath (Join-Path $outputPath "api-usages.csv")
$eventHandlers | Sort-Object App, Source, Line, Handler | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath (Join-Path $outputPath "event-handler-references.csv")
$navigation | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath (Join-Path $outputPath "navigation-references.csv")
$assets | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath (Join-Path $outputPath "asset-references.csv")
$issues | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath (Join-Path $outputPath "audit-issues.csv")
$summaries | ConvertTo-Json -Depth 4 | Set-Content -Encoding utf8 -LiteralPath (Join-Path $outputPath "audit-summary.json")

$summaries | Format-Table -AutoSize
Write-Host "Generated audit artifacts in $outputPath"
