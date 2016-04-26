# wp-mail-report
WP Plugin that stores all your sent emails in DB, and tracks when are opened by someone

## Usage

replace all your calls:

```
wp_mail(/* args... */);
```

with:

```
MailReportPlugin::send(/* same args of wp_mail... */);
```  
