param(
    [string]$DocsRoot = (Join-Path $PSScriptRoot "..\docs")
)

$generated = Join-Path $DocsRoot "generated"
$pages = Import-Csv -LiteralPath (Join-Path $generated "page-inventory.csv")
$apis = Import-Csv -LiteralPath (Join-Path $generated "api-catalog.csv")

$pageRows = for ($index = 0; $index -lt $pages.Count; $index++) {
    $page = $pages[$index]
    [pscustomobject]@{
        LegacyId = "PAGE-{0:D3}" -f ($index + 1)
        App = $page.App
        Package = $page.Package
        LegacyPath = $page.Path
        ArtifactComplete = $page.Complete
        Domain = ""
        Disposition = "UNREVIEWED"
        NewCapabilityId = ""
        AcceptanceCase = ""
        ReviewNote = ""
    }
}

$apiGroups = $apis | Group-Object App, Endpoint | Sort-Object { $_.Group[0].App }, { $_.Group[0].Endpoint }
$apiRows = for ($index = 0; $index -lt $apiGroups.Count; $index++) {
    $group = $apiGroups[$index].Group
    [pscustomobject]@{
        LegacyId = "API-{0:D3}" -f ($index + 1)
        App = $group[0].App
        LegacyEndpoint = $group[0].Endpoint
        Methods = (($group.Method | Sort-Object -Unique) -join "|")
        Exports = (($group.Export | Sort-Object -Unique) -join "|")
        Operation = (($group.Operation | Sort-Object -Unique) -join "|")
        Domain = ""
        Disposition = "UNREVIEWED"
        NewOperationId = ""
        AcceptanceCase = ""
        ReviewNote = ""
    }
}

if ($pageRows.Count -ne 185) {
    throw "Expected 185 legacy pages, found $($pageRows.Count)."
}
if ($apiRows.Count -ne 313) {
    throw "Expected 313 app-scoped legacy endpoints, found $($apiRows.Count)."
}

$pageRows | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath (Join-Path $DocsRoot "traceability-pages.csv")
$apiRows | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath (Join-Path $DocsRoot "traceability-apis.csv")

[pscustomobject]@{
    Pages = $pageRows.Count
    PageUnreviewed = @($pageRows | Where-Object Disposition -eq "UNREVIEWED").Count
    APIs = $apiRows.Count
    ApiUnreviewed = @($apiRows | Where-Object Disposition -eq "UNREVIEWED").Count
}
