## Initial tasks:

0) Register a Zoho CRM trial account;

   <h3>Using Laravel or Deluge:</h3>
1) Connect via Zoho CRM API;
2) Create an entry in the Contacts module in Zoho CRM;
3) Create an entry in the Deals (Potentials) module associated with an entry in Contacts.

## What is implemented

0. Create Zoho account - <b>done</b>
1. Connect to Zoho using Laravel - <b>done (Guzzle)</b>
2. Create an entry in the Contacts - <b>done (Guzzle POST request)</b>
2. Create an entry in the Deals module associated with an entry in Contacts - <b>done (Guzzle POST request)</b>

## How does it work

1. We've class ZohoAPIController which has Constructor inside. That constructor cares about our login state - so before initializing any action, we'll have access token (which would be automatically refreshed if needed)
2. Separate actions could be called using API. You can find endpoints description in the next section.
3. ZohoContactController and ZohoDealController having injected ZohoAPIController for handling all the Auth things.
4. I used session() helper to save user data between requests. As for me, for this task it was more simple than using DB.

### Endpoints

<h5>Task actions</h5>
- <b>add contact</b> - (/api/contact) <i>[POST]</i>. You can set some params if you want, but system has randomizer so you can call endpoint w/o any additional actions. <br>
- <b>add deal</b> - (/api/deal) <i>[POST]</i>. You can set some params if you want, but system has randomizer so you can call endpoint w/o any additional actions.

<h5>Auth</h5>
- <b>[login](/api/login)</b> <i>[GET]</i>. Just login to the system. You do not have to run it manually - system runs it in constructor if it's needed.<br>
- <b>[logout](/api/logout)</b> <i>[GET]</i>. If you change scopes - you have to logout.<br>
- <b>[refresh-access-token](/api/refresh-token)</b> <i>[GET]</i>. Same as login - you do not have to run it manually, __constructor will do all the job.<br>

## How to run the system

0. Pull this repo
1. Run <code>composer install</code>
2. **[Create <b>Self client</b>](https://api-console.zoho.com/)** with scopes at least these 2 scopes: <b>ZohoCRM.modules.Contacts.ALL, ZohoCRM.modules.Deals.ALL</b>  
3. Create .env file (copy it from .env.example) and specify <b>ZOHO_API_CLIENT_ID</b>, <b>ZOHO_API_CLIENT_SECRET</b>, <b>ZOHO_API_CLIENT_TOKEN</b> 
4. Run server. For example, using command <code>php artisan serve</code>
5. Choose any endpoint and have fun ;)

P.S. If you want to change Scopes - you'll have to change ZOHO_API_CLIENT_TOKEN in .env file and [GET] api/logout to clear stored token. <br>
P.P.S. You will need some tool like Postman for access endpoints (to send POST requests).

## Problems I faced with

1. Had a problem with auth, so the system didn't give me access_token, constantly returned undocumented error. The solution was creation of a new account. Seems like some internal bug.
