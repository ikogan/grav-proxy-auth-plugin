# Grav Proxy Authentication Plugin

This plugin adds the ability to authenticate users
by an upstream proxy server that properly sets HTTP headers.

> It's **very** important that the proxy also scrub
> the headers it will be setting so malicious users
> cannot just set headers on their request and bypass
> the proxy login.

## Installation

The proxy authentication plugin depends on the login
and form plugins. _The dependency on login may not be
necessary, determining that is TBD_. Eventually this
plugin should be available through GPM and will be
able to be installed along with it's dependnecies like
this:

```
$ bin/gpm install proxy-auth
```

## Configuration

This plugin has several important configuration
options that control its behavior:

```yaml
enabled: true                                   # Enable the plugin

header.username: 'X-Remote-User'                # HTTP header where the username can be found.
header.displayName: 'X-Remote-Display-Name'     # HTTP header where the full name can be found.
header.email: 'X-Remote-Email'                  # HTTP header where the e-mail address can be found.
header.groups: 'X-Remote-Groups'                # HTTP header where user groups can be found.

groupSeparator: ','                             # Character used to separate multiple groups
required.groups: []                             # Groups a user must have to be considered "authenticated"
url.login:                                      # URL to which to send users when they need authentication.
url.logout:                                     # URL to which to send users when they need to logout.
```

The login and logout URLs will replace the string
`${CURRENT_URL}` with the absolute URL the user is on.
**Note**: For this to work properly, the hostname
sent by the proxy should be the external hostname of
the site or the correct hostname should be configured
in Grav's settings.

Also, the group separate currently simply uses PHP's
`explode` function and therefore does not support
quoting. Hopefully this will be implemented in the
future.

## Usage

First, setup a reverse proxy that performs some kind
of authentication and sets, at least, a username
header. Authentication _will not_ fail if other data
is missing although users will be unable to become
admins without groups.

The plugin supports group management in the
traditional Grav way, see [the documentation](https://learn.getgrav.org/advanced/groups-and-permissions).
At the very least, you will want to assign a group
the following two permissions to create an admin
group:

- admin.login
- admin.super

You do _not_ currently need `site.login` as any user
that has a group matching `required.groups` will be
granted that access. If unset, all users are granted
that access. This will likely be removed in the future
in favor of explicitly granting `site.login` to
groups.

## Caveats

This plugin is brand new and has not been extensively
tested with all of the various functionality that
the login plugin provides. Namely, the following:

- No events are fired or caught by this module. This
  may mean that plugins relying on events related to
  user authentication may not work.
- User registration is not supported since that is
  expected to be handled by an external system.
- Users are not saved to disk. This means they cannot
  be edited and their profile cannot be viewed.
- Login/Logout URL handling is not yet completed. Only
  the options exist; they currently do nothing.
- Like registration, forgotten password and any
  related functionality is not supported.
- Until logout handling is implemented, the "Logout"
  button in the admin plugin will likely not do
  anything useful.
- The behavior of the plugin when a user exists in
  both the external system as well as in Grav itself
  is currently undefined.
- Groups that contain the "group separator" will not
  work and will likely break horribly.
- Standard limitations of HTTP header sizes and values
  apply.

## Todo

- Implement login/logout handling. Redirect users to
  login link when they are not authenticated in admin
  and mirror whatever behavior the login module uses
  for the rest of Grav. Manipulate the "Logout" link
  in admin to logout of both Grav and the admin
  module.
- Try to disable bits of the "login" module that
  do not make sense in this context or simply remove
  the dependency on the module if possible.
- Support displaying (but not editing) user profile
  information, overriding the admin module's default
  behavior.
- Remove the `required.groups` setting and rely on
  core group handling to decide if users are
  authenticated.
- Make sure behavior when users exist in Grav and
  an external system is consistent.
- Evaluate whether or not users should be created and
  saved in Grav.
