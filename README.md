# Online users REST API

#Endpoints:
## save_data - Save new user - can be inserted new user or update old one
#### Note that unique user is identified by email and session_id as each user can open many browsers/tabs with same email, so each one should be handled as different session
### Parameters:
#### name - user name
#### email - user email
#### session_id - user session_id

##list_users - Get list of active users, so here we need also to check which users are inactive too long
### Parameters:
#### session_id - optional parameter: user session_id - in order to mark current session as active


##get_user - Get user details for display in popup
### Parameters:
#### hash - user hash code to find requested user
#### session_id - optional parameter: user session_id - in order to mark current session as active
