/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
require('bootstrap/dist/css/bootstrap.css');
require('admin-lte/dist/css/AdminLTE.css');
require('admin-lte/dist/css/skins/skin-blue.css');
require('bootstrap-datepicker/dist/css/bootstrap-datepicker.css');
require('datatables.net-bs/css/dataTables.bootstrap.css');
require('@fortawesome/fontawesome-free/css/all.css');
require('../css/app.css');

// Need jQuery? Install it with "yarn add jquery", then uncomment to import it.

import $ from 'jquery';
global.$ = global.jQuery = $;
import 'bootstrap/dist/js/bootstrap';
import 'bootstrap-datepicker/dist/js/bootstrap-datepicker';
import 'admin-lte/dist/js/adminlte';
import 'datatables.net/js/jquery.dataTables';
import 'datatables.net-bs/js/dataTables.bootstrap';

import jsQR from 'jsqr';
// There is probably a better way to do this by putting the template JS code in a file that gets compiled, but this works for now.
global.jsQR = jsQR;

import moment from "moment";
global.moment = moment;

import './scanner'