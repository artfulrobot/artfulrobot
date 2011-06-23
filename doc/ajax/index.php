<html>
	<head>
		<title>Artful Robot Ajax Libraries</title>
	</head>
	<body>
		<script type="text/javascript" src="../externals/jquery.js" ></script>
		<script type="text/javascript" src="../../ajax/js/artfulrobot-ajax.js" ></script>
		<script type="text/javascript" >

var vehicle=artfulrobot.Class.create( {
    initialise: function(colour) {
		this.myName='vehicle';
		this.colour=colour;
    },
	describe: function() {
		return "This " + this.myName 
			+ " is " + this.colour ;
	},
});

var myVehicle=new vehicle('yellow');
t=myVehicle.describe(); // This vehicle is yellow 
document.write("<p>" + t + '</p>');

var car=artfulrobot.Class.create( vehicle, {
	initialise: function(colour) {
		// call superclass's initialise method
		this.$initialise(colour);
		// overwrite myName
		this.myName='car';
	},
	click: function() {
		jQuery(this.origContext).css({backgroundColor:'red'});
		alert(this.describe());
	},
	ajaxtest: function(){
		artfulrobot.Ajax.request( 
			{ request: 'test' },
			'testOutput',
			this.getCallback('ajaxtestRtn') );
	},
	ajaxtestRtn: function(){
		alert('ooh it worked');
	}

});


var myCar=new car('red');
t=myCar.describe(); // This car is red
document.write("<p>" + t + '</p>');
jQuery(function(){
		jQuery('#test').html('go').click( myCar.getCallback('click') );	
		jQuery('#ajaxtest').click( myCar.getCallback('ajaxtest') );	
		});
		</script>
		<div id='test'></div>
		<div><button id='ajaxtest'>Test Ajax</button></div>
		<div id='testOutput'></div>
	<body>
</html>
