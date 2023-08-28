1. `rm -rf mono.ocmod.zip && zip -r mono.ocmod.zip upload/`
2. `docker-compose up`
3. Go to http://localhost:8090 and go through with installation process
4. In http://localhost:8090/admin go to System->Settings->Edit->FTP

   Set following values:
   - FTP Host: `localhost`
   - FTP Port: `21`
   - FTP Username: `ftpuser`
   - FTP Password: `ftppassword`
   - FTP Root: `/var/www/html`
   - Enable FTP: `Yes`
5. Then Extension->Extension installer->Upload and pick mono.ocmod.zip
6. Then Extension->Modifications->Refresh
7. Module settings will be available at Extension->Payments
