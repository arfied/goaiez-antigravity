# Your single webhook endpoint, shared by every customer
@app.post("/webhooks/zernio")
async def zernio_webhook(request: Request):
    raw = await request.body()
    secret = os.environ["ZERNIO_WEBHOOK_SECRET"]
    computed = hmac.new(secret.encode(), raw, hashlib.sha256).hexdigest()

    if not hmac.compare_digest(computed, request.headers.get("X-Zernio-Signature", "")):
        return Response("Invalid signature", status_code=400)

    event = json.loads(raw)

    if already_processed(event["id"]):
        return {"ok": True}

    queue.enqueue(event)
    return {"ok": True}
