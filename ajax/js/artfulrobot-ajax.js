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
		console.log(arguments.callee.name,method);
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
				console.warn("calling ",method, " with args: ", args);
				// store original context (e.g. a jQuery object)
				context.origContext = this;
				// call method from object context
				context[method].apply(context, args);
			});
	}/*}}}*/
	return { create: create };
})();
