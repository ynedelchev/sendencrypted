




clean:
	rm -rf *.data *.json

len: 
	curl -kv -X POST -H "Authorization: Bearer test"  -H "Content-Type: text/plain" -H 'Content-Length: 12' https://localhost/sendencrypted/files -d '1234567890bcdefghijkBCDEFHIJK'
curl: 
	curl -kv -X POST -H "Authorization: Bearer test"  -H "Content-Type: text/plain" https://localhost/sendencrypted/files -d '1234567890bcdefghijkBCDEFHIJK'
get: 
	curl -kv -X GET -H "Authorization: Bearer test"  -H "Content-Type: text/plain" https://localhost/sendencrypted/files/dlV2cH4zSXtrQGd3Jm1SRHJoPTJmXnl9UnxuPFVvTUs=
