/*
Copyright 2011, Rich Lott

This file is part of Artful Robot Libraries.

Artful Robot Libraries is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by the Free
Software Foundation, either version 3 of the License, or (at your option) any
later version.

Artful Robot Libraries is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
details.

You should have received a copy of the GNU General Public License along with
Artful Robot Libraries.  If not, see <http://www.gnu.org/licenses/>.
*/


// main namespace
var artfulrobot = artfulrobot || {};

/**  class with inheritance
 *
 *  fooClass = artfulrobot.Class.create( obj );
 *  barClass = artfulrobot.Class.create( fooClass, obj );
 *
 *  fooObj = new fooClass();
 *  barObj = new barClass();
 *
 *  - obj is an object whose keys are property/method names.
 *  - within a method function 'this' will refer to the instance.
 *  - each method name that extends a method in the superclass
 *    can access the superclass method by prefixing with $,
 *    e.g. if barClass.say() extended fooClass.say() then barClass
 *    can call fooClass.say with this.$say()
 *  
 *  Callbacks, e.g. for jQuery:
 *  jQuery('.clickable).click( fooObj.getCallback( 'clickHandler' ));
 *
 *  When jQuery calls clickHandler, 'this' still points to fooObj,
 *  and the jQuery object that would otherwise have been 'this' is
 *  accessible at this.origContext
 *
 *  Nb. getCallback can take addition arguments, these become the 
 *  first arguments passed, with any others getting appended.
 *  e.g.
 *  for ( rowNo in rows ) {
 *      cb = fooObj.getCallback( 'rowClicked', rowNo );
 *      // attach handler to the row that includes the rowNo
 *      }
 *  
 */
artfulrobot.Class = (function() {
	var IS_DONTENUM_BUGGY = (function(){/*{{{*/
		for (var p in { toString: 1 }) {
		if (p === 'toString') return false;
		}
		return true;
		})();/*}}}*/
	function keys(object) {/*{{{*/
		if (typeof(object) !='object'
			|| (! object)
			|| (object instanceof Array) )
		{ throw("object given to keys is not an object"); }
		var results = [];
		for (var property in object) {
			if (object.hasOwnProperty(property)) {
				results.push(property);
			}
		}
		return results;
	}/*}}}*/
	function subclass() {};
	function create() {/*{{{*/
		var superclass = null, properties=[];

		// convert arguments to a more manageable array
		for (var i in arguments) properties.push(arguments[i]);

		// if first arg is function, store as superclass and remove it from properties
		if ( typeof(properties[0]) == 'function') {
			superclass = properties[0];
			properties.splice(0,1);
		}

		// klass is the class that we're crafting
		function klass() {
			this.initialise.apply(this, arguments);
		}

		// this keeps track of subclasses created
		// on an array on the class itself; not on the class's prototype
		// so the subclasses list is not inherited.
		klass.subclasses = [];

		// add in our getCallback method 
		klass.prototype.getCallback = getCallback;

		if (superclass) {
			// make subclass (empty method) inherit from superclass
			subclass.prototype = superclass.prototype;
			// make our class'es prototype somehow relate to subclass?
			// 'new' will return object.create(subclass.prototype) the subclass, 
			// why this intermediary step? why not klass.prototype=superclass.prototype
			// because calling "new superclass" would call superclass's constructor, which
			// was prototype's old problem (because we don't want to create any
			// objects here, just classes). So by wrapping around an empty function (constructor)
			// no constructor code gets run. 
			klass.prototype = new subclass;
			// store reference to new class on superclass's subclasses property
			superclass.subclasses.push(klass);
			// store reference to superclass('s prototype) in class property superclass
			// so subclass methods can accees the overridden superclass methods
			klass.prototype.superclass = superclass.prototype;
		}

		// add methods specified in the object passed as argument(s) to ARLClass.create()
		// nb. normally only one object is passed here.
		for (var i = 0, length = properties.length; i < length; i++)
			addMethods(klass, properties[i]);

		// set up blank initialise method if none already
		if (!klass.prototype.initialise)
			klass.prototype.initialise = function(){};

		// tell the klass object that its constructor is the
		// klass function defined above (as the starting point for klass)
		klass.prototype.constructor = klass;

		return klass;
	}/*}}}*/
	function addMethods(obj, source) {/*{{{*/
		var ancestor   = obj.prototype.superclass && obj.prototype.superclass,
			properties = keys(source);

		if (IS_DONTENUM_BUGGY) {
			if (source.toString != Object.prototype.toString)
				properties.push("toString");
			if (source.valueOf != Object.prototype.valueOf)
				properties.push("valueOf");
		}

		for (var i = 0, length = properties.length; i < length; i++) {
			var property = properties[i], value = source[property];

			if ( ancestor 
					&& typeof(value)=='function' 
					&& ancestor[property]
					&& typeof(ancestor[property])=='function'
			   ) {
				var superMethod=ancestor[property];
				obj.prototype['$'+property] = superMethod;

				value.valueOf = superMethod.valueOf.bind(superMethod);
				value.toString = superMethod.toString.bind(superMethod);
			}
			obj.prototype[property] = value;
		}

		return obj;
	}/*}}}*/
	function getCallback(method) {/*{{{*/
		// reference to our instance
		var context=this;
		// if we were passed any other arguments beyond method,
	    // these become the first args onto the callback
		var fixedArgs = Array.prototype.slice.call(arguments);
		// remove 1st, method argument
		fixedArgs.shift();

		return (function() {
				// prepend fixedArgs
				var args = fixedArgs.concat(Array.prototype.slice.call(arguments));
				// store original context (e.g. a jQuery object)
				context.origContext = this;
				// call method from object context
				context[method].apply(context, args);
			});
	}/*}}}*/
	return { create: create };
})();

artfulrobot.objectKeys = function( obj ) {
	var a=[];
	for (var k in obj) a.push(k);
	return a;
};
artfulrobot.countKeys = function( obj ) {
	// some browsers support this:
	if (obj.__count__) return obj.__count__;
	var a=0;
	for (var k in obj) a++;
	return a;
};

// artfulrobot.Ajax main ajax class {{{ 
artfulrobot.AjaxClass = artfulrobot.Class.create( 
{
/** Main Ajax class
 *  Deals with making ajax requests and parsing the responses
 *  into chunks of html(/text), js code, json object, error message
 *
 */
	initialise: function() // {{{
	{
		this.requestFrom = 'ajaxprocess.php'; // default
		this.requests = {};
		this.uniqueCounter = 1;
	},// }}}
	setRequestFrom: function (requestFrom) // {{{
	{
		// set the script to use for ajax requests.
		this.requestFrom = requestFrom || 'ajaxprocess.php'; // default
	}, // }}}
	liveRequests: function () // {{{
	{
		// count this.requests
		return artfulrobot.countKeys(this.requests);
	}, // }}}
	request: function( parmsObject, outputHtmlInsideElement, onSuccessCallback, statusText ) // {{{
	{
		/* We number requests, and return this number. 
		 * 
		 * Requests are stored in the object (hash) this.requests, which is an 
		 * object of objects containing statusText (optional string for debugging) and requestURI
		 *
		 */
		
		// need onSuccessCallback fundction, even if it is blank
		if ( typeof(onSuccessCallback) == 'undefined' )  onSuccessCallback=function(){};

		var requestId = 'ajax' + this.uniqueCounter++;

		var parms;
		if ( typeof parmsObject == 'string' ) parms = parmsObject;
		else parms = jQuery.param(parmsObject);

		// create absolute debug uri
		if (this.requestFrom.match( /^\// ))
			debugURI = window.location.protocol + '//' 
				+ window.location.host 
				+ this.requestFrom ;
		else // requestFrom is relative uri
		{
//			alert( this.requestFrom); alert( window.location.pathname); alert( window.location.pathname.replace( /^(.+\/).+?$/,'$1' ));
			debugURI = window.location.protocol + '//' 
				+ window.location.host 
				+ window.location.pathname.replace( /^(.+\/)[^\/]*?$/,'$1' )
				+ this.requestFrom ;
		}
		debugURI += '?' + parms + '&ajax=2',

		this.requests[ requestId ] = 
		{ 
			statusText:	( statusText ? statusText : '' ),
			debugURI: debugURI,
			outputHtmlInsideElement: outputHtmlInsideElement,
			onSuccessCallback: onSuccessCallback,
			live: 1,
			stage: 'request'
		};
		console.info(requestId + ' debug: ' + debugURI);

		// override this method if needed
		this.requestStarts( this.requests[ requestId ] );

		// make request
		jQuery.ajax( 
			this.requestFrom,
			{
				data:parms,
				type:'POST',
				failure: this.getCallback('onFailure',requestId), // these two ensure that the fail/success methods
				success: this.getCallback('onSuccess',requestId), // know which request failed/succeeded.
			} );
	}, // }}}
	requestEnded: function( requestId ) // {{{
	{
		this.requestEnds( this.requests[requestId] );
		delete this.requests[requestId];
		//this.requests[requestId].live = 0;
	}, // }}}
	onFailure: function(requestId, t)  // {{{
	{ 
		alert( requestId + ': Problem! error: '+t.status+'--'+t.statusText );
		t.responseText='';
		this.requestEnded();
	}, // }}}
	onSuccess: function(requestId, t) // {{{
	{
		/* This returns 
		{objectLength:nnn, errorLength:nnn, codeLength:nnn }
		object
		error
		code
		text
		*/
		var rqst = this.requests[requestId];
		rqst.stage = 'response-parse-stage-1';
		rqst.t = t;
		try
		{
			var rsp = t;
			var i;
			i   = rsp.indexOf('}')+1;
			if ( ! i) 
			{
				rqst.stage = 'response-parse-stage-1 FAIL';
				this.seriousError('Bad ajax response. I cannot recognise a structure definition in the response. Expected } but none found.', requestId, rsp );
				return;
			}
			rqst.stage = 'response-parse-stage-2';
			// unpack json into chunks variable
			chunks = rsp.substr(0,i);
			if ( ! /^\{.*?\}$/.test(chunks)) 
			{
				rqst.stage = 'response-parse-stage-2 FAIL';
				this.seriousError('Bad ajax response. I cannot recognise a structure definition in the response. Structure definition failed regexp.', requestId, rsp );
				return;
			}
			try { 
				rqst.stage = 'response-parse-stage-3';
				var chunks = jQuery.parseJSON(rsp.substr(0,i));
			}
			catch(e) 
			{ 
				rqst.stage = 'response-parse-stage-3 FAIL';
				this.seriousError('Bad ajax response. Javascript failed to parse structure definition found in the response', requestId, rsp );
				return;
			}


			// json object returned?
			var obj = rsp.substr(i,chunks.objectLength);
			i += parseInt(chunks.objectLength);
			if (obj) 
			{
				try {
					rqst.stage = 'response-parse-json';
					obj = jQuery.parseJSON(obj);
				}
				catch(e) 
				{ 
					rqst.stage = 'response-parse-json FAIL';
					this.seriousError('Bad ajax response. Javascript failed to parse returned JSON object', requestId, rsp );
					return;
			   	}
			}
			else obj = {};

			rqst.stage = 'response-parse-error and text';
			// error returned?
			var error = rsp.substr(i,chunks.errorLength);
			i+=parseInt(chunks.errorLength);

			// text returned?
			var text = rsp.substr(i);
		}
		catch(e)
		{
			rqst.stage = 'response-parse FAIL';
			console.error(e);
			this.seriousError('Bad ajax response.', requestId, rsp );
			return;
		}

		// show any non-serious error to user
		if (error) alert(error);

		rqst.stage = "replace element innerHTML...";
		// update the element if given, and if there's html 
		// returned from the ajax call
		if (text!='' && rqst.outputHtmlInsideElement!='') 
		{
			var node = rqst.outputHtmlInsideElement;
			if ( typeof node == 'string' ) node = jQuery('#'+node);
			else node = jQuery(node);
			node.html(text);
		}
		// do extra stuff
		rqst.stage = "callback";
		try
		{
			this.requests[requestId].onSuccessCallback(obj, text);
		}
		catch(e)
		{
			this.seriousError('Failed on callback function. Request ' + requestId + ' ' + Object.toJSON(this.requests[requestId]) , requestId, rsp );
			return;
		}

		// execute the js code returned by the ajax call
		if ( js_to_run && debug(requestId+": (WARNING: DEPRECIATED)"+js_to_run)) 
		{
			alert("Used depreciated 'code' return. Please re-write code.");
//			   	eval(js_to_run);
		}
//			else debug(requestId+' 4/4 (no code to eval (good, this functionality is depreciated)) ');

		this.requestEnded(requestId);
	}, // }}}
	seriousError: function( errorMsg, requestId, responseText ) // {{{
	{
		var errorReport = window.open();
		errorReport.document.write( 
				'<html><head><title>Error report</title><style>h2 {color:#800 ;font-size:16px;} h3 {font-size:14px;margin-bottom:0;} div { border:solid 1px #888; background-color:#ffe;padding:1em; } </style></head>'
				+'<body>'
				+ '<h2>Error: ' + errorMsg + '</h3><p><a href="'
				+ this.requests[requestId].debugURI
				+ '" >Re-issue request with php debugging on</a></p>' 
				+ '<h3>Request:</h3><div>'
				+ this.requests[requestId].debugURI.replace( /^(.+?)\?.+$/, '$1' )
				+ '<br /><pre>'
				+ this.requests[requestId].debugURI.replace( /^(.+?)\?(.+)$/, '$2' ).replace( /&/g, '<br />' )
				+ '</pre></div>'
				+ '<h3>Response received:</h3><div>'
				+ responseText
				+'</div></body></html>');

	}, // }}}
	requestEnds: function( requestObj ) { },
	requestStarts: function( requestObj ) { },
	dump:function() // {{{
	{
		console.log(this.requests);
	} // }}}
}); // }}}
jQuery(function(){ 
		// set up default instance
		artfulrobot.Ajax = new artfulrobot.AjaxClass();
	});
