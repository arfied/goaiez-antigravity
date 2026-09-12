# request_url: the URL your handler received; parse_qs decodes the values
query = parse_qs(urlparse(request_url).query)
temp_token = query["tempToken"][0]
user_profile = json.loads(query["userProfile"][0])

pages = client.connect.list_facebook_pages(
    profile_id=profile_id,
    temp_token=temp_token,
)
```
</Tab>
<Tab value="curl">
```bash
