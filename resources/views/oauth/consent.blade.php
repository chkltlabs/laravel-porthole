<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorize Porthole</title>
</head>
<body>
    <main>
        <h1>Authorize {{ $clientName }}</h1>
        <p>This client is asking for the <code>mcp:use</code> scope on the Porthole issuer. The token it receives is not an application API token.</p>
        <p>Redirect host: <code>{{ $redirectHost }}</code></p>
        <form method="POST" action="{{ route('porthole.oauth.approve') }}">
            @csrf
            <input type="hidden" name="request" value="{{ $requestId }}">
            <button type="submit" name="decision" value="approve">Approve</button>
            <button type="submit" name="decision" value="deny">Deny</button>
        </form>
    </main>
</body>
</html>
