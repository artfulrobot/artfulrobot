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
//artfulrobot.defineClass {{{
/**  artfulrobot.defineClass class with inheritance
 *
 *  fooClass = artfulrobot.defineClass( obj );
 *  barClass = artfulrobot.defineClass( fooClass, obj );
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
artfulrobot.defineClass = function() {/*{{{*/
	// Define local functions we need:
	var IS_DONTENUM_BUGGY = (function(){/*{{{*/
		for (var p in { toString: 1 }) {
		if (p === 'toString') return false;
		}
		return true;
		})();/*}}}*/
	var subclass = function() {};
	// arlClass is the class that we're crafting xxx ie?
	function arlClass() { this.initialise.apply(this, arguments); }
	var addMethods = function(obj, source) {/*{{{*/
		var ancestor   = obj.prototype.superclass && obj.prototype.superclass,
			properties = artfulrobot.objectKeys(source);

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

				// xxx value.valueOf = superMethod.valueOf.bind(superMethod);
				// value.toString = superMethod.toString.bind(superMethod);
			}
			obj.prototype[property] = value;
		}

		return obj;
	};/*}}}*/

	// Start process
	var superclass = null, properties=[];
	// convert arguments to a more manageable array
	// decided jQuery read cleaner than this: properties = Array.prototype.slice.call(arguments);
	properties = jQuery.makeArray(arguments);

	// if first arg is function, store as superclass and remove it from properties
	if ( typeof(properties[0]) == 'function') {
		superclass = properties[0];
		properties.splice(0,1);
	}

	// this keeps track of subclasses created
	// on an array on the class itself; not on the class's prototype
	// so the subclasses list is not inherited.
	arlClass.subclasses = [];

	// add in our getCallback method 
	arlClass.prototype.getCallback = function (method) {/*{{{*/
		if (! this[method]) throw new artfulrobot.Exception("getCallback: Object does not have '"+method+"'",this);
		// reference to our instance
		var context=this;
		// if we were passed any other arguments beyond method,
	    // these become the first args onto the callback
		var fixedArgs = jQuery.makeArray(arguments);
		// remove 1st, method argument
		fixedArgs.shift();

		return (function() {
				// prepend fixedArgs
				var args = fixedArgs.concat(jQuery.makeArray(arguments));
				// store original context (e.g. a jQuery object)
				context.origContext = this;
				// call method from object context
				context[method].apply(context, args);
			});
	}/*}}}*/

	// set up nice toString
	arlClass.prototype.toString = function(){ return "arlClass object id:" + this.myId; };

	if (superclass) {
		// make subclass (empty method) inherit from superclass
		subclass.prototype = superclass.prototype;
		// make our class'es prototype somehow relate to subclass?
		// 'new' will return object.create(subclass.prototype) the subclass, 
		// why this intermediary step? why not arlClass.prototype=superclass.prototype
		// because calling "new superclass" would call superclass's constructor, which
		// was prototype's old problem (because we don't want to create any
		// objects here, just classes). So by wrapping around an empty function (constructor)
		// no constructor code gets run. 
		arlClass.prototype = new subclass;
		// store reference to new class on superclass's subclasses property
		superclass.subclasses.push(arlClass);
		// store reference to superclass('s prototype) in class property superclass
		// so subclass methods can accees the overridden superclass methods
		arlClass.prototype.superclass = superclass.prototype;
	}

	// add methods specified in the object passed as argument(s) to ARLClass.create()
	// nb. normally only one object is passed here.
	for (var i = 0, length = properties.length; i < length; i++)
		addMethods(arlClass, properties[i]);

	// set up blank initialise method if none already
	if (!arlClass.prototype.initialise)
		arlClass.prototype.initialise = function(){};

	// tell the arlClass object that its constructor is the
	// arlClass function defined above (as the starting point for arlClass)
	arlClass.prototype.constructor = arlClass;

	return arlClass;
}/*}}}*/
// }}}
artfulrobot.countKeys = function( obj ) {/*{{{*/
	// some browsers support this:
	if (obj.__count__) return obj.__count__;
	var a=0;
	for (var k in obj) a++;
	return a;
};/*}}}*/
artfulrobot.objectKeys = function( obj ) {/*{{{*/
	var a=[];
	for (var k in obj) a.push(k);
	return a;
};/*}}}*/
artfulrobot.objectToSerializeArray = function( obj ) {/*{{{*/
	// returns the object as an array of objects as returned by jQuery.serializeArray()
	// { a: 5, b: 6} --> [ { name:'a', value: 5}, {key:'b', value:6} ];
	var a=[];
	for (var k in obj) a.push( {name: k, value:obj[k]} );
	return a;
};/*}}}*/
artfulrobot.serializeArrayToObject = function( a ) {/*{{{*/
	// opposite of objectToSerializeArray
	var l = a.length;
	var o = {};
	for (var i=0;i<l;i++) o[ a[i]['name'] ] = a[i]['value'];
	return o;
};/*}}}*/
artfulrobot.typeOf = function( thing ) // {{{
{
	var type = typeof thing;
	if ( type === 'object' ) // arrays and objects and null!
	{
		if ( ! thing ) return 'null';
		else if (   thing instanceof Array ) return 'array';
		else return 'object';
	}
	else return type;
} // }}}
artfulrobot.htmlentities = {/*{{{*/
	hellip : '\u2026',
	nbsp   : '\u00a0',
	otimes : '\u2297',
	pound  : '\u00A3'
};/*}}}*/
artfulrobot.createFragmentFromArray = function( arr ) // {{{
{
/* example: call with AAA.
   AAA is either a string or an array of BBBs
   BBB is either a string, or an object CCC
   CCC is like { element: 'div', content: AAA }

		html.appendChild( createFragmentFromArray( [
				{ element: 'div', style: 'border:solid 1px red;', content: 'goodbye' },
				{ element: 'div', style: 'border:solid 1px red;', content: [
					'aaaaaaaaaaaaa',
					{ element: 'div', style: 'background-color:#fee', content: 'blah' },
//					{ element: 'div', style: 'background-color:#ffe', content: 'doob' },
					'bbbbbbbbbbbbb'
				]}
			] ));
   */
	var myDebug=0;
	var type = artfulrobot.typeOf(arr);
	if ( type === 'string' ) 
	{
		myDebug && console.log('Called with string: returning TextNode:' + arr );
		return document.createTextNode( arr );
	}
	else if ( type === 'null' ) 
	{ 
		alert("Encountered a null, expected string, array, object");
		return; 
	}
	// convert single objects to arrays
	else if ( type === 'object' ) 
	{
		myDebug && console.log('Called with object: putting it in an array');
		arr = [ arr ];
	}
	else if ( type !== 'array' )
	{
		alert("error: type " + type + " encountered in createFragmentFromArray expected: string, array, object");
		return;
	}

	var arrLen = arr.length;
	var df = document.createDocumentFragment();
	var tmp;
	for (var i=0;i<arrLen;i++) // process each element of array: either an object or a string
	{
		var part = arr[i];
		type = artfulrobot.typeOf( part );
		myDebug && console.warn('part:', part);
		if (type == 'string' || type == 'number')
		{
			myDebug && console.log('appending TextNode:' + part );
			df.appendChild( document.createTextNode( String(part) ) );
		}
		else if (type == 'object' ) 
		{
			myDebug && console.log('Creating ' + part.element + ' element');
			// create element
			tmp = document.createElement( part.element );
			// set attributes
			for (var key in part)
			{
				myDebug && console.log('key: ', key);

				if (String(',onblur,onchange,onclick,ondblclick,onfocus,onkeydown,onkeypress,onkeyup,onmousedown,onmousemove,onmouseout,onmouseover,onmouseup,onresize,onscroll,onmouseenter,onmouseleave,').indexOf(','+key+',')>-1)
					{  
						myDebug && console.log('adding as event listener '+part[key]);
						// old: tmp[key] = part[key];

						// if part[key] is an array, then the first part is the handler and the 2nd is data for jQuery.
						var evtName = key.substr(2); // lop off the 'on'
						if (artfulrobot.typeOf(part[key]) == 'array')
						{
							// include the data in the jQuery bind call:
							jQuery(tmp).bind(evtName,part[key][1],part[key][0]);
						}
						else {
							jQuery(tmp).bind(evtName,part[key]);
						}
					}
				else if (key=='element' || key=='content' ) continue;
				else if (key=='innerHTML' ) { myDebug && console.log('setting innerHTML ');tmp.innerHTML = part[key] ;}
				else { myDebug && console.log('setting attribute '+key);tmp.setAttribute(key, part[key] );}
			}
			myDebug && console.log('created ' + part.element + ' element: ' + tmp + ' ' + df.childNodes.length);
			// append children
			if (part.content) 
			{
				myDebug && console.log('Recursing for ', part.content);
				tmp.appendChild( artfulrobot.createFragmentFromArray( part.content ) );
				myDebug && console.log('Back from recursion');
			}
			myDebug && console.log('Adding to fragment...');
			// add this to our document fragment
			df.appendChild( tmp );
			myDebug && console.log('...success');
		}
		else 
		{
			alert("Error:\nartfulrobot.createElement '" + type + "' type encountered within content array, expected string or object");
		}
	}
	myDebug && console.log('Complete');
	return df;
} // }}}
artfulrobot.Exception = artfulrobot.defineClass( {/*{{{*/
	initialise: function()
	{
		this.details = jQuery.makeArray(arguments);
		this.message = this.details[0];
		console && console.error && console.error.apply(this, ['exception args: '].concat(this.details) );
	},
	details:[],
	toString:function()
	{
		var msg="artfulrobot.Exception :"+this.message;
		for (x in this.details) msg += "\n" + this.details[x];
		return msg;
	}
});/*}}}*/
/* functions for making forms easier */
artfulrobot.getRadioValue = function( radioGroupName ) // {{{
{
	if (! radioGroupName) throw new artfulrobot.Exception( "getRadioValue called without a radio group name. Got:", radioGroupName);
	var selectedElement = jQuery('input[name="' + radioGroupName+ '"]:checked');
	if ( selectedElement.length  ) return selectedElement[0].value;
	return null; 
} // }}}
artfulrobot.setSelectOptionByValue = function( selectNode, val ) // {{{
{
	jQuery( selectNode ).find('option').each( function(i,optNode)
		{ 
			optNode.selected = (optNode.value==val); 
		});
} // }}}
artfulrobot.setSelectOptionByText = function( selectNode, text ) // {{{
{
	jQuery( selectNode ).find('option').each( function(i,optNode)
		{
			optNode.selected = (jQuery(optNode).text()==text); 
		});
} // }}}

// artfulrobot.AjaxClass main ajax class {{{ 
artfulrobot.AjaxClass = artfulrobot.defineClass( 
{
/** Main Ajax class
 *  Deals with making ajax requests and parsing the responses
 *  into chunks of html(/text), js code, json object, error message
 *
 */
	initialise: function( opts ) // {{{
	{
		this.requestFrom = 'ajaxprocess.php'; // default
		this.method = 'get'; // default
		this.requests = {};
		this.uniqueCounter = 1;

		opts = opts || {};
		if (opts.requestFrom) this.setRequestFrom(opts.requestFrom);
		if (opts.method) this.setMethod(opts.method);
	},// }}}
	setRequestFrom: function (requestFrom) // {{{
	{
		// set the script to use for ajax requests.
		this.requestFrom = requestFrom || 'ajaxprocess.php'; // default
	}, // }}}
	setMethod: function (postOrGet) // {{{
	{
		if (postOrGet == 'get' || postOrGet == 'post') this.method=postOrGet;
		else throw new Error("ajax method must be post or get"); 
	}, // }}}
	liveRequests: function () // {{{
	{
		// count this.requests
		return artfulrobot.countKeys(this.requests);
	}, // }}}
	request: function( givenParams, outputHtmlInsideElement, onSuccessCallback, method ) // {{{
	{
		/* We number requests, and return this number. 
		 * 
		 * Requests are stored in the object (hash) this.requests, which is an 
		 * object of objects containing various details
		 *
		 */
		
		// need onSuccessCallback fundction, even if it is blank
		if ( typeof(onSuccessCallback) == 'undefined' 
				|| onSuccessCallback == false
				|| onSuccessCallback === null
				)  onSuccessCallback=function(){};

		var requestId = 'ajax' + this.uniqueCounter++;

		var params;
		var paramsType = artfulrobot.typeOf(givenParams);
		// given (what we assume to be a) url-encoded string, just pass it along
		if ( paramsType == 'string' ) params = givenParams;
		// given an object { key1: val1, key2: val2 ... }
		else if (paramsType == 'object' )
		{
			// map false|undefined to zls because it's likely parsed as a string
			// the other end, so 'false' == true.
			jQuery.each(givenParams, function(i,v)
				{ if (v===false || v===undefined) givenParams[i]=''; });
			// url-encode it
			params = jQuery.param(givenParams);
		}
		// given array of objects with {name:..., value:....}, {...}
		// as comes from jQuery('form').serializeArray()
		else if (paramsType == 'array')
		{
			jQuery.each(givenParams, function(i,o)
				{ if (o.value===false || o.value===undefined) givenParams[i].value=''; });
			// url-encode it
			params = jQuery.param(givenParams);
		}
		else
		{
			throw Error("artfulrobot.AjaxClass.request called with params as unkonwn type: '"+paramsType+"'");
			return;
		}

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
		debugURI += '?' + params + '&debug=1',

		this.requests[ requestId ] = 
		{ 
			debugURI: debugURI,
			outputHtmlInsideElement: outputHtmlInsideElement,
			onSuccessCallback: onSuccessCallback,
			live: 1,
			stage: 'request'
		};

		// override this method if needed
		this.requestStarts( this.requests[ requestId ] );

		// make request
		jQuery.ajax( 
			this.requestFrom,
			{
				data:params,
				type:method || this.method,
				failure: this.getCallback('onFailure',requestId), // these two ensure that the fail/success methods
				success: this.getCallback('onSuccess',requestId) // know which request failed/succeeded.
			} );

		return requestId;
	}, // }}}
	requestEnded: function( requestId ) // {{{
	{
		this.requestEnds( this.requests[requestId] );
		delete this.requests[requestId];
		//this.requests[requestId].live = 0;
	}, // }}}
	onFailure: function(requestId, t)  // {{{
	{ 
		alert( requestId + ': Problem! error: '+t.status);
		t.responseText='';
		this.requestEnded();
	}, // }}}
	onSuccess: function(requestId, t) // {{{
	{
		/* This returns 
		{objectLength:nnn, errorLength:nnn, codeLength:nnn }
		object
		error
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
			this.seriousError('Bad ajax response.', requestId, rsp );
			return;
		}

		// show any non-serious error to user
		if (error) alert(error);

		rqst.stage = "replace element innerHTML...";
		// update the element if given, and if there's html 
		// returned from the ajax call
		if (text!='' && rqst.outputHtmlInsideElement) 
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
			// we add this to the object so the caller can know whether it's the one they were expecting to process!
			obj.requestId = requestId;
			this.requests[requestId].onSuccessCallback(obj, text);
		}
		catch(e)
		{
			this.seriousError('Failed on callback function. See artfulrobot.ajax.requests.'+requestId, requestId,rsp);
			return;
		}

		this.requestEnded(requestId);
	}, // }}}
	seriousError: function( errorMsg, requestId, responseText ) // {{{
	{
		console && console.error && console.error( errorMsg, requestId, " ",  responseText);
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

		errorReport.document.close();
	}, // }}}
	requestEnds: function( requestObj ) { },
	requestStarts: function( requestObj ) { },
	dump:function() // {{{
	{
		if (console && console.log ) console.log(this.requests);
		else alert("No console object available");
	} // }}}
});
// set up default instance
artfulrobot.ajax = new artfulrobot.AjaxClass();
// }}}

// artfulrobot.ARLObject  -- all arlObjects inherit from this {{{
/** ARLObject documentation {{{
 *
 * Usual set up for a web application is to start with a single one:
 * var myApp = new artfulrobot.ARLObject();
 * 
 * and then to add in sub object(s) with
 * myApp.addSubObject( 'yourExtendedClassName', [optional args Array] );

 * As well as each instance of ARLObject keeping a record of its own sub
 * objects, *all* objects are given a unique id and registered globally on the
 * artfulrobot.arlObjects object. This id (accessible through obj.getId()
 * method) can be passed in ajax requests and means that code can be returned
 * from the server that directly calls a specific object's method. Obv. it's
 * nicer to do all your binding with js, but if needed you can do like
 * <span onclick='artfulrobot.arlObjects.$yourId.someMethodName()' >

 * Each object can communicate with others via "shouting". 
 * object's .shout method passes a sigType (string) and an dataObj (object) up
 * the inheritance chain until an object has no parent. Then the same data is
 * then fed down to the .abstractHear method, which calls the .hear method
 * before feeding the same data down to all the subObjects' .abstractHear
 * methods, recursively.

 * The idea is that a .hear method can listen out for signals by the value of
 * sigType.  Nb. An object does not .hear itself shout.

 * The dataObj object can contain whatever data is needed for anything to deal
 * with the shouted signal. Eg. if some field has been updated, the dataObject
 * might include the fieldname and the new value. Or, if needed it could
 * include the full record, including the updated field.
 * As a js. object it can also include callback functions etc. if really needed.

 * Objects reference their parent through .myParent However, the idea is that
 * objects can be re-used, so if an object relies on a parent method existing
 * then it means *all* potential parents need that method, which is not cool.
 * Better is to use signals.

 * Signals

 * An object can contain methods that deal with a particular signal from
 * a particular object.  These should be connected to the
 * 2nd object with the .connectSignal method. Then, when the 2nd object shouts
 * with that signal, the signal goes only to the connected slot(s), instead of
 * being broadcast to all objects. 

 * Usually it is a parent that connects one of its slots with a signal from a child object.
 * 
 * =======Example 1: connecting sub-object's signal to parent's slot ===========
 * usefulObject...
 * getSomeName: function
 * {
 * 	this.addSubObject( NameFinderGUI, [], 'myNameFinderGUI' );
 * 	this.myNameFinderGUI.connectSignal( 'nameFound', this.getCallback('nameFoundHandler') );
 * }
 * nameFoundHandler: function( sigType, dataObj )
 * {
 * 	// note "this" refers to this object, as you'd expect
 * 	// because you created the callback with getCallback()
 * 	var name = dataObj.name;

 * 	// do something with name
 * 	this.myNamesList.push( name );

 * 	// destroy the nameFinderGUI object
 * 	this.destroySubObject( this.myNameFinderGUI );
 * }
 * 
 * nameFinderGUI ...
 * userFinishedSelectingName: function()
 * {
 * 	this.shout( 'nameFound', {name:this.nameTheySelected} );
 * }
 * ==================================================
 * 
 *
 * }}}	*/
// Global collection of arlObjects
artfulrobot.arlObjects = {};
// Main class: all ARLObject classes must inherit from this
artfulrobot.ARLObject = artfulrobot.defineClass(
{
	nextId: 0,// counter for all these objects (regardless of which collection they may be in) so no two get same id
	debugLevel: 0,
	sharedMethods: {},
	initialise: function( parentItem, myName, session, argsArray )// {{{
	{
		// needs to know the parent object, in case it needs to shout (to siblings)
		// this will be undefined for the first object created.
		this.myParent = parentItem;

		// class name
		this.name = ( typeof myName == 'undefined' 
				? 'ARLObject' // first/main object will be this one
				: myName);

		if (this.debugLevel>1) console.info(this.name + '.initialize');
		if (this.debugLevel>1) console.log(' session: ', session );

		// increment nextId so all Ajah_rl_AbstractObjects will have unique ids
		artfulrobot.ARLObject.prototype.nextId++;
		this.myId = 'arlObjId' + this.nextId;  

		// container object  - by defining it here it's not part of the prototype
		this.subObjects   = {}; 

		// signals - methods that send signals from other objects
		this.signals = {};

		this.initialiseSession(session);

		// local initialize:
		if (this.debugLevel>1) console.info(this.name + '.calling localInitialise');
		if (artfulrobot.typeOf(argsArray)!='array') argsArray = [];
		this.localInitialise.apply(this, argsArray);
		if (this.debugLevel>1) console.info(this.name + '.initialise done. Claimed id: ' + this.myId );
		if (this.debugLevel>1) console.log('ends');
	}, // }}}
	initialiseSession: function(session) // override if necessary {{{
	{
		// console.log(this.name + '.initializeSession() called')
		this._SESSION = (typeof session == 'object' ? session : {} );
	}, // override this including setting this.name to the name of the object that extends this }}}
	localInitialise: function(  )// abstract - must override {{{
	{ }, // override this including setting this.name to the name of the object that extends this }}}
	getId: function() { return this.myId; },
	shout: function( sigType, dataObj ) // {{{
	{ 
		if (this.debugLevel>1) console.info(this.name + '.shout called for '+sigType);

		// append reference to self to dataObject
		if ( typeof dataObj == 'undefined' ) dataObj = {};
		if ( typeof dataObj.shoutedBy == 'undefined' ) dataObj.shoutedBy = this;

		// check if this sigType is connected to particular slot(s)
		if ( typeof this.signals[ sigType ] != 'undefined' )
		{
			var signalTo = this.signals[ sigType ];
			var l =signalTo.length;
			if (this.debugLevel>1) console.log( 'signalling direct to ' + l + ' slots ');
			for ( i=0; i<l ; i++ ) signalTo[i]( sigType, dataObj );
		}
		else if (this.myParent ) this.myParent.shout( sigType, dataObj );  // pass on shouts to parent.
		else this.abstractHear( sigType, dataObj ); // _we_ are oldest ancestor start hearing and telling children

		if (this.debugLevel>1) console.log('ends');
	}, // }}}
	abstractHear: function(sigType, dataObj)  // {{{
	{
		// default, pass it down to subObjects
		if (this.debugLevel>0) console.info(this.name + '.hear with sigType: ' + sigType);
		if (dataObj.shoutedBy.myId != this.myId ) this.hear(sigType, dataObj);
		if (this.debugLevel>1) console.log('back from local hear, calling abstractHear on subobjects..');
		for (var id in this.subObjects)
			this.subObjects[id].abstractHear(sigType,dataObj); 
		if (this.debugLevel>1) console.log('ends');
	}, // }}}
	hear: function(sigType, dataObj)  // override this {{{
	{ }, // }}}
	addSubObject: function( objClass, argsArray, localAlias ) // {{{
	{ 
		if (this.debugLevel>1) console.info(this.name + '.addSubObject called for objClass: ' + objClass);
		// objClass should be string name for the class
		// argsArray should be an array that is passed to the class constructor

		// if adding an aliased subobject, we can only have one, so if there's already one, get rid of it.
		if (localAlias && this[localAlias]) this.destroySubObject(localAlias);

		// get name of class
		var soName = 'unknown';
		if (typeof objClass == 'string') 
		{
			soName   = objClass; 		// store name
			if ( ! window[objClass] ) throw new artfulrobot.Exception("artfulrobot.ARLObject.addSubObject: Class '"+objClass+"' is unknown");
			objClass = window[objClass];// reference 'class' itself
		}
		if ( ! artfulrobot.typeOf(objClass) == 'object' ) throw new artfulrobot.Exception("artfulrobot.ARLObject.addSubObject: supplied class is not a class.", objClass);
		// create new object
		// Arguments:
		// 		this, 		reference will be saved in object's myParent property
		// 		soName,		name of subObject class, stored in object's name property
		//		session 	object given from this to the sub object
		//		argsArray	as passed to us
		var newObj = new objClass( this, soName, this.getSessionForSubObject(soName), argsArray ); 
		if (this.debugLevel>1) console.log(this.name + '.addSubObject: new object created', newObj);
		
		// connect child signal 'saveSession' to our slotSaveSession
		newObj.connectSignal( 'saveSession', this.getCallback('saveSubObjSession') );

		// keep reference to our subObject in our subObjects collection
		this.subObjects[newObj.myId] = newObj ;  // used for shouting.

		// keep global reference to object so it can be accessed directly
		// by knowing it's 'arlObjId' 
		artfulrobot.arlObjects[newObj.myId] = newObj; 			// global reference 

		// aliasing makes a property with that name that references the
		// object. When the object is destroyed, this is set false.
		if (localAlias)
		{
			newObj.aliasedAs = localAlias;
			this[localAlias] = newObj;
		}


		if (this.debugLevel>1) console.info(this.name + '.addSubObject done');
		if (this.debugLevel>1) console.log('ends');
		// return new object for chaining.
		return newObj; 
	}, // }}}

	getSessionForSubObject: function ( soName ) // {{{
	{ 
		var x = {};
		if (   typeof this._SESSION.subObjects != 'undefined'  )
		{
		    if ( typeof this._SESSION.subObjects[soName] != 'undefined' )
			{
			   x= this._SESSION.subObjects[soName];
			}
			else if (this.debugLevel>1) console.log(this.name + '.getSessionForSubObject _SESSION.subObjects undefined for ' + soName );
		}
		else if (this.debugLevel>1) console.log(this.name + '.getSessionForSubObject _SESSION.subObjects undefined' );
		if (this.debugLevel>1) console.log(this.name + '.getSessionForSubObject: ' , this._SESSION );
		if (this.debugLevel>1) console.info(this.name + '.getSessionForSubObject: returning ' , x );
		return x;
	}, // }}}
	saveSession: function ( newFriendlyName, newTitle ) // {{{
	{ 

		/**saveSession shouts 'saveSession' signal which should be received directly 
		 * by parent object's saveSubObjSession()
		 */
		if (this.debugLevel>1) console.info( this.name+'.saveSession');
		if (this.debugLevel>1) console.log(  this._SESSION );
		if ( this.myParent ) this.shout('saveSession', 
				{
					'id' : this.myId,
					'name' : this.name,
					'sessionObj' : this._SESSION,
					'newFriendlyName' : newFriendlyName,
					'newTitle' : newTitle
				} );
	}, // }}}
	saveSubObjSession : function (sigType, obj) // hears subObject's saveSession call {{{
	{ 
		if (this.debugLevel>1) console.info(this.name + '.slotSaveSession');
		if (this.debugLevel>1) console.info(obj);
		// obj contains:
		// 	'id' subObject id
		// 	'sessionObj' : _SESSION
		if ( typeof this._SESSION.subObjects == 'undefined' ) this._SESSION.subObjects = {};
		this._SESSION.subObjects[ obj.name ] = obj.sessionObj;

		// pass up the chain of parents
		// the main controller parent would override the saveSession method
		// with code that would stringify it's this._SESSION variable
		// and send an ajax request to store it.
		this.saveSession( obj.newFriendlyName, obj.newTitle );
	}, // }}}

	destroySubObject: function ( objOrObjId ) // {{{
	{ 
		if (!objOrObjId) return;
		// takes a string id or the object
		var argType = artfulrobot.typeOf(objOrObjId);
		var objId;
		var obj;
		
		if (argType == 'string' ) 
		{
			objId = objOrObjId;
			obj = this.subObjects[objId];
			if (typeof('ojb')=='undefined')
			   throw new artfulrobot.Exception(
					   'destroySubObject called for "'
					   +objOrObjId+'" which is not valid id. I have: '
					   +artfulrobot.objectKeys(this.subObjects).join(', '),
				this.subObjects);
		}
		else if (argType == 'object') 
		{
			objId = objOrObjId.getId();
			obj = objOrObjId;
		}
		else  throw "destroySubObject got type " + argType + " needed id or object of an ARL object (PS. also, I ignore null/false)";

		if (this.debugLevel>1) console.info(this.name + '.destroySubObject called for '+objId);
		// possible for this to fail if the calling code has somehow messed something up,
		// so we do the test for the subObject before destroy()ing it.
		if (obj)
		{
			// if we have a localAlias to this object, we need to drop that
			var localAlias = obj.aliasedAs ;

			obj.destroy();
			delete this.subObjects[objId];	// our reference
			delete artfulrobot.arlObjects[objId];			// global reference
			delete obj;
			if (localAlias) this[localAlias]=false;
		}
	}, // }}}
	destroySubObjects: function () // {{{
	{ 
		if (this.debugLevel>-1) console.info(this.name + '.destroySubObjects '+this.myId, this.subObjects);
		// recursive bit: call .destroySubObject() on any subObjects
		for (var id in this.subObjects) 
			this.destroySubObject( id );
	}, // }}}
	destroy: function()  // {{{
	{
		if (this.debugLevel>1) console.log( this.name + '->destroy called');
		// called by parent's destroySubObject method.
		// we are being destroyed.
		this.destroySubObjects();

		// call .cleanup() which should be over-ridden by 
	    // classes extending this one to remove html etc. that they created.
		this.cleanup();	
	}, // }}}
	cleanup: function( )// -override this- {{{
	{ }, // }}}
	connectSignal: function( sigType, receivingSlotMethod ) // {{{
	{
		if ( typeof this.signals[sigType] == 'undefined' ) this.signals[sigType] = [];
		this.signals[sigType].push( receivingSlotMethod );
	}, // }}}
	toString: function() // {{{
	{
		msg =  "[" + this.name + "] Object";
		return msg;
	}, // }}}
	setSessionDefaults: function( defs ) // {{{
	{
		/** setSessionDefaults checks that each key of defs exists in this._SESSION
		 *  and if not, creates it with the value from defs.
		 *  Does not overwrite anything that is there.
		 */
		if ( typeof this._SESSION == 'undefined' ) this._SESSION = {};
		for (key in defs)
		{
			if ( typeof this._SESSION[ key ] == 'undefined' )
				this._SESSION[ key ] = defs[ key ];
		};
	}, // }}}
	getObjectByName: function (name) //{{{
	{
		// go through all objects looking for one of class 'name'
		// return reference to it.
		// or null
		for (i in artfulrobot.arlObjects)
		{
			if ( artfulrobot.arlObjects[i].name == name ) return artfulrobot.arlObjects[i];
		}
		return undefined;
	} // }}}
});
// }}}
ARLKeepAlive = artfulrobot.defineClass( artfulrobot.ARLObject, // {{{
{
	localInitialise: function()  // {{{
	{ 
		this.pingInABit();
	}, // }}}
	pingInABit:function()//{{{
	{
		// ping every 2 minutes
		this.timer = setTimeout( this.getCallback('ping'), 1000*60*2 );
	},//}}}
	ping: function()  // {{{
	{ 
		artfulrobot.ajax.request( {arlClass: 'keepalive'}, '', this.getCallback('pingRtn') );
	}, // }}}
	pingRtn: function(obj)  // {{{
	{ 
		if (! obj.success)
		{
			alert("Keep alive failed!");
			throw "Keep alive failed!";
		}
		else 
		{	
			this.pingInABit();
		}
	}, // }}}
	cleanup: function()  // {{{
	{ 
		if (this.timer) clearTimeout( this.timer );
	} // }}}
}); // }}}

