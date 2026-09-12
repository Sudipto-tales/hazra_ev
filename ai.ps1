param(
    [Parameter(Mandatory=$false)]
    [string]$Prompt,

    [Parameter(Mandatory=$false)]
    [string]$File
)

if ($File) {
    $Prompt = Get-Content $File -Raw
}

if (-not $Prompt) {
    Write-Host "Please provide a prompt or file."
    exit
}

$apiKey = [Environment]::GetEnvironmentVariable("OPENROUTER_API_KEY")
if (-not $apiKey) {
    Write-Host "OPENROUTER_API_KEY is not set." -ForegroundColor Red
    exit 1
}

$body = @{
    model = "openrouter/free"

    messages = @(
        @{
            role = "user"
            content = $Prompt
        }
    )
} | ConvertTo-Json -Depth 10

try {
    $response = Invoke-RestMethod `
        -Uri "https://openrouter.ai/api/v1/chat/completions" `
        -Method Post `
        -Headers @{
            Authorization = "Bearer $apiKey"
            "HTTP-Referer" = "http://localhost"
            "X-Title" = "Hazra EV AI"
        } `
        -ContentType "application/json" `
        -Body $body

    Write-Host ""
    Write-Host $response.choices[0].message.content
    Write-Host ""
}
catch {
    Write-Host "ERROR:" -ForegroundColor Red
    Write-Host $_.Exception.Message

    if ($_.ErrorDetails.Message) {
        Write-Host $_.ErrorDetails.Message
    }
}