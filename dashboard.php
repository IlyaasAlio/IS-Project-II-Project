<?php
// Sends each user to the dashboard for their role.
require 'config.php';
require_login();
redirect(role_home(current_user()['role']));
