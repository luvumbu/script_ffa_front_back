// <div id="test"></div>
class Atribute { // Create a class
	constructor(id) { // Constructor
		this.id = id; // Class body 
	}
	exe_atribute(atribute, nameAtribute) {
		document.getElementById(this.id).setAttribute(atribute, nameAtribute);
	}
	get title(){
return document.getElementById(this.id).title;
	}
	set title(title){
		document.getElementById(this.id).title=title;
	}
}
 
// var monAtribute = new Atribute("test");
// monAtribute.exe_atribute("class","red");
 