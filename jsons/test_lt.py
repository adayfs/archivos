import requests

url = "https://libretranslate.com/translate"
payload = {
    "q": "Hello world",
    "source": "en",
    "target": "es",
    "format": "text"
}

resp = requests.post(url, json=payload, timeout=20)
print("Status:", resp.status_code)
print("Body:", resp.text)
