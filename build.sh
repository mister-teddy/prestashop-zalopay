# Build source code to installable zip file
zip zalopay.zip zalopay -r -x '*.git*'

# Update new code over FTP
. ./.env.sh
lftp -e "set ftp:ssl-allow no; mirror -R zalopay public_html/modules/zalopay; quit" -u $FTP_USER,$FTP_PASSWD $FTP_HOST
echo Deployed successfully to $FTP_HOST