/*
 *
 * Client Encryption of Forms.
 *
 * Includes: 
 * * RSA and ECC in JavaScript | http://www-cs-students.stanford.edu/~tjw/jsbn/
 * * Stanford Javascript Crypto Library | http://crypto.stanford.edu/sjcl/
 * * JSON in JavaScript | http://www.JSON.org/
 * 
 * Version: 0_1_14
 * Author:  ADYEN (c) 2014
 <!DOCTYPE html>
 <html lang="en">
 <head>
 <title>Example Payment Form</title>
 <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
 <link rel="stylesheet" href="css/cse-example-form.css" type="text/css" />
 </head>
 <body>

 <form method="POST" action="#handler" id="adyen-encrypted-form">
 <fieldset>
 <legend>Card Details</legend>
 <div class="field">
 <label for="adyen-encrypted-form-number">
 <span>Card Number</span>
 <input type="text" id="adyen-encrypted-form-number" value="5555444433331111" size="20" autocomplete="off" data-encrypted-name="number" />
 </label>
 <span id="cardType"></span>
 </div>
 <div class="field">
 <label for="adyen-encrypted-form-cvc">
 <span>CVC</span>
 <input type="text" id="adyen-encrypted-form-cvc" value="737" size="4" maxlength="4" autocomplete="off" data-encrypted-name="cvc" />
 </label>
 </div>

 <div class="field">
 <label for="adyen-encrypted-form-holder-name">
 <span>Card Holder Name</span>
 <input type="text" id="adyen-encrypted-form-holder-name" value="John Doe" size="20" autocomplete="off" data-encrypted-name="holderName" />
 </label>
 </div>

 <div class="field">
 <label for="adyen-encrypted-form-expiry-month">
 <span>Expiration (MM/YYYY)</span>
 <input type="text" value="06"   id="adyen-encrypted-form-expiry-month" maxlength="2" size="2" autocomplete="off" data-encrypted-name="expiryMonth" /> /
 </label>
 <!-- Do not use two input elements inside a single label. This will cause focus issues on the seoncd and latter fields using the mouse in various browsers -->
 <input type="text" value="2016" id="adyen-encrypted-form-expiry-year" maxlength="4" size="4" autocomplete="off" data-encrypted-name="expiryYear" />
 </div>
 <input type="hidden" id="adyen-encrypted-form-expiry-generationtime" value="generate-this-server-side" data-encrypted-name="generationtime" />
 <input type="submit" value="Submit" />
 </fieldset>
 </form>

 <!-- How to use the Adyen encryption client-side JS library -->
 <!-- N.B. Make sure the library is *NOT* loaded in the "head" of the HTML document -->
 <script type="text/javascript" src="js/adyen.encrypt.min.js?0_1_14"></script>

 <script type="text/javascript">

 // generate time client side for testing only... Don't deploy on a
 // real integration!!!
 document.getElementById('adyen-encrypted-form-expiry-generationtime').value = new Date().toISOString();

 // the form element to encrypt
 var form    = document.getElementById('adyen-encrypted-form');

 // the public key
 var key     =   "10001|80C7821C961865FB4AD23F172E220F819A5CC7B9956BC3458E2788"
 + "F9D725B07536E297B89243081916AAF29E26B7624453FC84CB10FC7DF386"
 + "31B3FA0C2C01765D884B0DA90145FCE217335BCDCE4771E30E6E5630E797"
 + "EE289D3A712F93C676994D2746CBCD0BEDD6D29618AF45FA6230C1D41FE1"
 + "DB0193B8FA6613F1BD145EA339DAC449603096A40DC4BF8FACD84A5D2CA5"
 + "ECFC59B90B928F31715A7034E7B674E221F1EB1D696CC8B734DF7DE2E309"
 + "E6E8CF94156686558522629E8AF59620CBDE58327E9D84F29965E4CD0FAF"
 + "A38C632B244287EA1F7F70DAA445D81C216D3286B09205F6650262CAB415"
 + "5F024B3294A933F4DC514DE0B5686F6C2A6A2D";

 var options = {};

 // Enable basic field validation (default is true)
 // The submit button will be disabled when fields
 // proof to be invalid. The form submission will be
 // prevented as well.
 // options.enableValidations = true;


 // Always have the submit button enabled (default is false)
 // Leave the submit button enabled, even in case
 // of validation errors.
 // options.submitButtonAlwaysEnabled = false;

 // Ignore non-numeric characters in the card number field (default
 // is true)
 // The payment handling ignores non-numeric characters for the card
 // field.
 // By default non-numeric characters will also be ignored while
 // validating
 // the card number field. This can be disabled for UX reasons.
 // options.numberIgnoreNonNumeric = true;

 // Ignore CVC validations for certain bins. Supply a comma separated
 // list.
 // options.cvcIgnoreBins = '6703'; // Ignore CVC for BCMC


 // Use a different attribute to identify adyen fields
 // Note that the attributes needs to start with data-.
 // options.fieldNameAttribute = 'data-encrypted-name';

 // Set a element that should display the card type
 options.cardTypeElement = document.getElementById('cardType');

 // the form will be encrypted before it is submitted
 adyen.encrypt.createEncryptedForm( form, key, options);

 </script>
 </body>
 </html>
 *
 */

( function (root, fnDefine) {

    // Prevent libraries to die on AMD patterns
    var define, exports;

    /* typedarray.js */
    (function(){try{var b=[new Uint8Array(1),new Uint32Array(1),new Int32Array(1)];return}catch(g){}function f(e,a){return this.slice(e,a)}function c(j,e){if(arguments.length<2){e=0}for(var a=0,h=j.length;a<h;++a,++e){this[e]=j[a]&255}}function d(e){var a;if(typeof e==="number"){a=new Array(e);for(var h=0;h<e;++h){a[h]=0}}else{a=e.slice(0)}a.subarray=f;a.buffer=a;a.byteLength=a.length;a.set=c;if(typeof e==="object"&&e.buffer){a.buffer=e.buffer}return a}try{window.Uint8Array=d}catch(g){}try{window.Uint32Array=d}catch(g){}try{window.Int32Array=d}catch(g){}})();(function(){if("btoa" in window){return}var a="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";window.btoa=function(g){var e="";var f,d;for(f=0,d=g.length;f<d;f+=3){var k=g.charCodeAt(f)&255;var j=g.charCodeAt(f+1)&255;var h=g.charCodeAt(f+2)&255;var c=k>>2,b=((k&3)<<4)|(j>>4);var m=f+1<d?((j&15)<<2)|(h>>6):64;var l=f+2<d?(h&63):64;e+=a.charAt(c)+a.charAt(b)+a.charAt(m)+a.charAt(l)}return e}})();

    /* For older browser make sure to include a shim for the JSON object */

    /* json2.js */
    if(typeof JSON!=="object"){JSON={}}(function(){function f(n){return n<10?"0"+n:n}if(typeof Date.prototype.toJSON!=="function"){Date.prototype.toJSON=function(key){return isFinite(this.valueOf())?this.getUTCFullYear()+"-"+f(this.getUTCMonth()+1)+"-"+f(this.getUTCDate())+"T"+f(this.getUTCHours())+":"+f(this.getUTCMinutes())+":"+f(this.getUTCSeconds())+"Z":null};String.prototype.toJSON=Number.prototype.toJSON=Boolean.prototype.toJSON=function(key){return this.valueOf()}}var cx=/[\u0000\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,escapable=/[\\\"\x00-\x1f\x7f-\x9f\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,gap,indent,meta={"\b":"\\b","\t":"\\t","\n":"\\n","\f":"\\f","\r":"\\r",'"':'\\"',"\\":"\\\\"},rep;function quote(string){escapable.lastIndex=0;return escapable.test(string)?'"'+string.replace(escapable,function(a){var c=meta[a];return typeof c==="string"?c:"\\u"+("0000"+a.charCodeAt(0).toString(16)).slice(-4)})+'"':'"'+string+'"'}function str(key,holder){var i,k,v,length,mind=gap,partial,value=holder[key];if(value&&typeof value==="object"&&typeof value.toJSON==="function"){value=value.toJSON(key)}if(typeof rep==="function"){value=rep.call(holder,key,value)}switch(typeof value){case"string":return quote(value);case"number":return isFinite(value)?String(value):"null";case"boolean":case"null":return String(value);case"object":if(!value){return"null"}gap+=indent;partial=[];if(Object.prototype.toString.apply(value)==="[object Array]"){length=value.length;for(i=0;i<length;i+=1){partial[i]=str(i,value)||"null"}v=partial.length===0?"[]":gap?"[\n"+gap+partial.join(",\n"+gap)+"\n"+mind+"]":"["+partial.join(",")+"]";gap=mind;return v}if(rep&&typeof rep==="object"){length=rep.length;for(i=0;i<length;i+=1){if(typeof rep[i]==="string"){k=rep[i];v=str(k,value);if(v){partial.push(quote(k)+(gap?": ":":")+v)}}}}else{for(k in value){if(Object.prototype.hasOwnProperty.call(value,k)){v=str(k,value);if(v){partial.push(quote(k)+(gap?": ":":")+v)}}}}v=partial.length===0?"{}":gap?"{\n"+gap+partial.join(",\n"+gap)+"\n"+mind+"}":"{"+partial.join(",")+"}";gap=mind;return v}}if(typeof JSON.stringify!=="function"){JSON.stringify=function(value,replacer,space){var i;gap="";indent="";if(typeof space==="number"){for(i=0;i<space;i+=1){indent+=" "}}else{if(typeof space==="string"){indent=space}}rep=replacer;if(replacer&&typeof replacer!=="function"&&(typeof replacer!=="object"||typeof replacer.length!=="number")){throw new Error("JSON.stringify")}return str("",{"":value})}}if(typeof JSON.parse!=="function"){JSON.parse=function(text,reviver){var j;function walk(holder,key){var k,v,value=holder[key];if(value&&typeof value==="object"){for(k in value){if(Object.prototype.hasOwnProperty.call(value,k)){v=walk(value,k);if(v!==undefined){value[k]=v}else{delete value[k]}}}}return reviver.call(holder,key,value)}text=String(text);cx.lastIndex=0;if(cx.test(text)){text=text.replace(cx,function(a){return"\\u"+("0000"+a.charCodeAt(0).toString(16)).slice(-4)})}if(/^[\],:{}\s]*$/.test(text.replace(/\\(?:["\\\/bfnrt]|u[0-9a-fA-F]{4})/g,"@").replace(/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g,"]").replace(/(?:^|:|,)(?:\s*\[)+/g,""))){j=eval("("+text+")");return typeof reviver==="function"?walk({"":j},""):j}throw new SyntaxError("JSON.parse")}}}());


    /* base64.js */
    var b64map="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";var b64padchar="=";function hex2b64(d){var b;var e;var a="";for(b=0;b+3<=d.length;b+=3){e=parseInt(d.substring(b,b+3),16);a+=b64map.charAt(e>>6)+b64map.charAt(e&63)}if(b+1==d.length){e=parseInt(d.substring(b,b+1),16);a+=b64map.charAt(e<<2)}else{if(b+2==d.length){e=parseInt(d.substring(b,b+2),16);a+=b64map.charAt(e>>2)+b64map.charAt((e&3)<<4)}}while((a.length&3)>0){a+=b64padchar}return a}function b64tohex(e){var c="";var d;var a=0;var b;for(d=0;d<e.length;++d){if(e.charAt(d)==b64padchar){break}v=b64map.indexOf(e.charAt(d));if(v<0){continue}if(a==0){c+=int2char(v>>2);b=v&3;a=1}else{if(a==1){c+=int2char((b<<2)|(v>>4));b=v&15;a=2}else{if(a==2){c+=int2char(b);c+=int2char(v>>2);b=v&3;a=3}else{c+=int2char((b<<2)|(v>>4));c+=int2char(v&15);a=0}}}}if(a==1){c+=int2char(b<<2)}return c}function b64toBA(e){var d=b64tohex(e);var c;var b=new Array();for(c=0;2*c<d.length;++c){b[c]=parseInt(d.substring(2*c,2*c+2),16)}return b};

    /* jsbn.js */
    var dbits;var canary=244837814094590;var j_lm=((canary&16777215)==15715070);function BigInteger(e,d,f){if(e!=null){if("number"==typeof e){this.fromNumber(e,d,f)}else{if(d==null&&"string"!=typeof e){this.fromString(e,256)}else{this.fromString(e,d)}}}}function nbi(){return new BigInteger(null)}function am1(f,a,b,e,h,g){while(--g>=0){var d=a*this[f++]+b[e]+h;h=Math.floor(d/67108864);b[e++]=d&67108863}return h}function am2(f,q,r,e,o,a){var k=q&32767,p=q>>15;while(--a>=0){var d=this[f]&32767;var g=this[f++]>>15;var b=p*d+g*k;d=k*d+((b&32767)<<15)+r[e]+(o&1073741823);o=(d>>>30)+(b>>>15)+p*g+(o>>>30);r[e++]=d&1073741823}return o}function am3(f,q,r,e,o,a){var k=q&16383,p=q>>14;while(--a>=0){var d=this[f]&16383;var g=this[f++]>>14;var b=p*d+g*k;d=k*d+((b&16383)<<14)+r[e]+o;o=(d>>28)+(b>>14)+p*g;r[e++]=d&268435455}return o}if(j_lm&&(navigator.appName=="Microsoft Internet Explorer")){BigInteger.prototype.am=am2;dbits=30}else{if(j_lm&&(navigator.appName!="Netscape")){BigInteger.prototype.am=am1;dbits=26}else{BigInteger.prototype.am=am3;dbits=28}}BigInteger.prototype.DB=dbits;BigInteger.prototype.DM=((1<<dbits)-1);BigInteger.prototype.DV=(1<<dbits);var BI_FP=52;BigInteger.prototype.FV=Math.pow(2,BI_FP);BigInteger.prototype.F1=BI_FP-dbits;BigInteger.prototype.F2=2*dbits-BI_FP;var BI_RM="0123456789abcdefghijklmnopqrstuvwxyz";var BI_RC=new Array();var rr,vv;rr="0".charCodeAt(0);for(vv=0;vv<=9;++vv){BI_RC[rr++]=vv}rr="a".charCodeAt(0);for(vv=10;vv<36;++vv){BI_RC[rr++]=vv}rr="A".charCodeAt(0);for(vv=10;vv<36;++vv){BI_RC[rr++]=vv}function int2char(a){return BI_RM.charAt(a)}function intAt(b,a){var d=BI_RC[b.charCodeAt(a)];return(d==null)?-1:d}function bnpCopyTo(b){for(var a=this.t-1;a>=0;--a){b[a]=this[a]}b.t=this.t;b.s=this.s}function bnpFromInt(a){this.t=1;this.s=(a<0)?-1:0;if(a>0){this[0]=a}else{if(a<-1){this[0]=a+this.DV}else{this.t=0}}}function nbv(a){var b=nbi();b.fromInt(a);return b}function bnpFromString(h,c){var e;if(c==16){e=4}else{if(c==8){e=3}else{if(c==256){e=8}else{if(c==2){e=1}else{if(c==32){e=5}else{if(c==4){e=2}else{this.fromRadix(h,c);return}}}}}}this.t=0;this.s=0;var g=h.length,d=false,f=0;while(--g>=0){var a=(e==8)?h[g]&255:intAt(h,g);if(a<0){if(h.charAt(g)=="-"){d=true}continue}d=false;if(f==0){this[this.t++]=a}else{if(f+e>this.DB){this[this.t-1]|=(a&((1<<(this.DB-f))-1))<<f;this[this.t++]=(a>>(this.DB-f))}else{this[this.t-1]|=a<<f}}f+=e;if(f>=this.DB){f-=this.DB}}if(e==8&&(h[0]&128)!=0){this.s=-1;if(f>0){this[this.t-1]|=((1<<(this.DB-f))-1)<<f}}this.clamp();if(d){BigInteger.ZERO.subTo(this,this)}}function bnpClamp(){var a=this.s&this.DM;while(this.t>0&&this[this.t-1]==a){--this.t}}function bnToString(c){if(this.s<0){return"-"+this.negate().toString(c)}var e;if(c==16){e=4}else{if(c==8){e=3}else{if(c==2){e=1}else{if(c==32){e=5}else{if(c==4){e=2}else{return this.toRadix(c)}}}}}var g=(1<<e)-1,l,a=false,h="",f=this.t;var j=this.DB-(f*this.DB)%e;if(f-->0){if(j<this.DB&&(l=this[f]>>j)>0){a=true;h=int2char(l)}while(f>=0){if(j<e){l=(this[f]&((1<<j)-1))<<(e-j);l|=this[--f]>>(j+=this.DB-e)}else{l=(this[f]>>(j-=e))&g;if(j<=0){j+=this.DB;--f}}if(l>0){a=true}if(a){h+=int2char(l)}}}return a?h:"0"}function bnNegate(){var a=nbi();BigInteger.ZERO.subTo(this,a);return a}function bnAbs(){return(this.s<0)?this.negate():this}function bnCompareTo(b){var d=this.s-b.s;if(d!=0){return d}var c=this.t;d=c-b.t;if(d!=0){return(this.s<0)?-d:d}while(--c>=0){if((d=this[c]-b[c])!=0){return d}}return 0}function nbits(a){var c=1,b;if((b=a>>>16)!=0){a=b;c+=16}if((b=a>>8)!=0){a=b;c+=8}if((b=a>>4)!=0){a=b;c+=4}if((b=a>>2)!=0){a=b;c+=2}if((b=a>>1)!=0){a=b;c+=1}return c}function bnBitLength(){if(this.t<=0){return 0}return this.DB*(this.t-1)+nbits(this[this.t-1]^(this.s&this.DM))}function bnpDLShiftTo(c,b){var a;for(a=this.t-1;a>=0;--a){b[a+c]=this[a]}for(a=c-1;a>=0;--a){b[a]=0}b.t=this.t+c;b.s=this.s}function bnpDRShiftTo(c,b){for(var a=c;a<this.t;++a){b[a-c]=this[a]}b.t=Math.max(this.t-c,0);b.s=this.s}function bnpLShiftTo(j,e){var b=j%this.DB;var a=this.DB-b;var g=(1<<a)-1;var f=Math.floor(j/this.DB),h=(this.s<<b)&this.DM,d;for(d=this.t-1;d>=0;--d){e[d+f+1]=(this[d]>>a)|h;h=(this[d]&g)<<b}for(d=f-1;d>=0;--d){e[d]=0}e[f]=h;e.t=this.t+f+1;e.s=this.s;e.clamp()}function bnpRShiftTo(g,d){d.s=this.s;var e=Math.floor(g/this.DB);if(e>=this.t){d.t=0;return}var b=g%this.DB;var a=this.DB-b;var f=(1<<b)-1;d[0]=this[e]>>b;for(var c=e+1;c<this.t;++c){d[c-e-1]|=(this[c]&f)<<a;d[c-e]=this[c]>>b}if(b>0){d[this.t-e-1]|=(this.s&f)<<a}d.t=this.t-e;d.clamp()}function bnpSubTo(d,f){var e=0,g=0,b=Math.min(d.t,this.t);while(e<b){g+=this[e]-d[e];f[e++]=g&this.DM;g>>=this.DB}if(d.t<this.t){g-=d.s;while(e<this.t){g+=this[e];f[e++]=g&this.DM;g>>=this.DB}g+=this.s}else{g+=this.s;while(e<d.t){g-=d[e];f[e++]=g&this.DM;g>>=this.DB}g-=d.s}f.s=(g<0)?-1:0;if(g<-1){f[e++]=this.DV+g}else{if(g>0){f[e++]=g}}f.t=e;f.clamp()}function bnpMultiplyTo(c,e){var b=this.abs(),f=c.abs();var d=b.t;e.t=d+f.t;while(--d>=0){e[d]=0}for(d=0;d<f.t;++d){e[d+b.t]=b.am(0,f[d],e,d,0,b.t)}e.s=0;e.clamp();if(this.s!=c.s){BigInteger.ZERO.subTo(e,e)}}function bnpSquareTo(d){var a=this.abs();var b=d.t=2*a.t;while(--b>=0){d[b]=0}for(b=0;b<a.t-1;++b){var e=a.am(b,a[b],d,2*b,0,1);if((d[b+a.t]+=a.am(b+1,2*a[b],d,2*b+1,e,a.t-b-1))>=a.DV){d[b+a.t]-=a.DV;d[b+a.t+1]=1}}if(d.t>0){d[d.t-1]+=a.am(b,a[b],d,2*b,0,1)}d.s=0;d.clamp()}function bnpDivRemTo(n,h,g){var w=n.abs();if(w.t<=0){return}var k=this.abs();if(k.t<w.t){if(h!=null){h.fromInt(0)}if(g!=null){this.copyTo(g)}return}if(g==null){g=nbi()}var d=nbi(),a=this.s,l=n.s;var v=this.DB-nbits(w[w.t-1]);if(v>0){w.lShiftTo(v,d);k.lShiftTo(v,g)}else{w.copyTo(d);k.copyTo(g)}var p=d.t;var b=d[p-1];if(b==0){return}var o=b*(1<<this.F1)+((p>1)?d[p-2]>>this.F2:0);var A=this.FV/o,z=(1<<this.F1)/o,x=1<<this.F2;var u=g.t,s=u-p,f=(h==null)?nbi():h;d.dlShiftTo(s,f);if(g.compareTo(f)>=0){g[g.t++]=1;g.subTo(f,g)}BigInteger.ONE.dlShiftTo(p,f);f.subTo(d,d);while(d.t<p){d[d.t++]=0}while(--s>=0){var c=(g[--u]==b)?this.DM:Math.floor(g[u]*A+(g[u-1]+x)*z);if((g[u]+=d.am(0,c,g,s,0,p))<c){d.dlShiftTo(s,f);g.subTo(f,g);while(g[u]<--c){g.subTo(f,g)}}}if(h!=null){g.drShiftTo(p,h);if(a!=l){BigInteger.ZERO.subTo(h,h)}}g.t=p;g.clamp();if(v>0){g.rShiftTo(v,g)}if(a<0){BigInteger.ZERO.subTo(g,g)}}function bnMod(b){var c=nbi();this.abs().divRemTo(b,null,c);if(this.s<0&&c.compareTo(BigInteger.ZERO)>0){b.subTo(c,c)}return c}function Classic(a){this.m=a}function cConvert(a){if(a.s<0||a.compareTo(this.m)>=0){return a.mod(this.m)}else{return a}}function cRevert(a){return a}function cReduce(a){a.divRemTo(this.m,null,a)}function cMulTo(a,c,b){a.multiplyTo(c,b);this.reduce(b)}function cSqrTo(a,b){a.squareTo(b);this.reduce(b)}Classic.prototype.convert=cConvert;Classic.prototype.revert=cRevert;Classic.prototype.reduce=cReduce;Classic.prototype.mulTo=cMulTo;Classic.prototype.sqrTo=cSqrTo;function bnpInvDigit(){if(this.t<1){return 0}var a=this[0];if((a&1)==0){return 0}var b=a&3;b=(b*(2-(a&15)*b))&15;b=(b*(2-(a&255)*b))&255;b=(b*(2-(((a&65535)*b)&65535)))&65535;b=(b*(2-a*b%this.DV))%this.DV;return(b>0)?this.DV-b:-b}function Montgomery(a){this.m=a;this.mp=a.invDigit();this.mpl=this.mp&32767;this.mph=this.mp>>15;this.um=(1<<(a.DB-15))-1;this.mt2=2*a.t}function montConvert(a){var b=nbi();a.abs().dlShiftTo(this.m.t,b);b.divRemTo(this.m,null,b);if(a.s<0&&b.compareTo(BigInteger.ZERO)>0){this.m.subTo(b,b)}return b}function montRevert(a){var b=nbi();a.copyTo(b);this.reduce(b);return b}function montReduce(a){while(a.t<=this.mt2){a[a.t++]=0}for(var c=0;c<this.m.t;++c){var b=a[c]&32767;var d=(b*this.mpl+(((b*this.mph+(a[c]>>15)*this.mpl)&this.um)<<15))&a.DM;b=c+this.m.t;a[b]+=this.m.am(0,d,a,c,0,this.m.t);while(a[b]>=a.DV){a[b]-=a.DV;a[++b]++}}a.clamp();a.drShiftTo(this.m.t,a);if(a.compareTo(this.m)>=0){a.subTo(this.m,a)}}function montSqrTo(a,b){a.squareTo(b);this.reduce(b)}function montMulTo(a,c,b){a.multiplyTo(c,b);this.reduce(b)}Montgomery.prototype.convert=montConvert;Montgomery.prototype.revert=montRevert;Montgomery.prototype.reduce=montReduce;Montgomery.prototype.mulTo=montMulTo;Montgomery.prototype.sqrTo=montSqrTo;function bnpIsEven(){return((this.t>0)?(this[0]&1):this.s)==0}function bnpExp(h,j){if(h>4294967295||h<1){return BigInteger.ONE}var f=nbi(),a=nbi(),d=j.convert(this),c=nbits(h)-1;d.copyTo(f);while(--c>=0){j.sqrTo(f,a);if((h&(1<<c))>0){j.mulTo(a,d,f)}else{var b=f;f=a;a=b}}return j.revert(f)}function bnModPowInt(b,a){var c;if(b<256||a.isEven()){c=new Classic(a)}else{c=new Montgomery(a)}return this.exp(b,c)}BigInteger.prototype.copyTo=bnpCopyTo;BigInteger.prototype.fromInt=bnpFromInt;BigInteger.prototype.fromString=bnpFromString;BigInteger.prototype.clamp=bnpClamp;BigInteger.prototype.dlShiftTo=bnpDLShiftTo;BigInteger.prototype.drShiftTo=bnpDRShiftTo;BigInteger.prototype.lShiftTo=bnpLShiftTo;BigInteger.prototype.rShiftTo=bnpRShiftTo;BigInteger.prototype.subTo=bnpSubTo;BigInteger.prototype.multiplyTo=bnpMultiplyTo;BigInteger.prototype.squareTo=bnpSquareTo;BigInteger.prototype.divRemTo=bnpDivRemTo;BigInteger.prototype.invDigit=bnpInvDigit;BigInteger.prototype.isEven=bnpIsEven;BigInteger.prototype.exp=bnpExp;BigInteger.prototype.toString=bnToString;BigInteger.prototype.negate=bnNegate;BigInteger.prototype.abs=bnAbs;BigInteger.prototype.compareTo=bnCompareTo;BigInteger.prototype.bitLength=bnBitLength;BigInteger.prototype.mod=bnMod;BigInteger.prototype.modPowInt=bnModPowInt;BigInteger.ZERO=nbv(0);BigInteger.ONE=nbv(1);

    /* prng4.js */
    function Arcfour(){this.i=0;this.j=0;this.S=new Array}function ARC4init(c){var a,d,b;for(a=0;a<256;++a){this.S[a]=a}d=0;for(a=0;a<256;++a){d=d+this.S[a]+c[a%c.length]&255;b=this.S[a];this.S[a]=this.S[d];this.S[d]=b}this.i=0;this.j=0}function ARC4next(){var a;this.i=this.i+1&255;this.j=this.j+this.S[this.i]&255;a=this.S[this.i];this.S[this.i]=this.S[this.j];this.S[this.j]=a;return this.S[a+this.S[this.i]&255]}function prng_newstate(){return new Arcfour}Arcfour.prototype.init=ARC4init;Arcfour.prototype.next=ARC4next;var rng_psize=256;

    /* rng.js */
    var rng_state;var rng_pool;var rng_pptr;function rng_seed_int(a){rng_pool[rng_pptr++]^=a&255;rng_pool[rng_pptr++]^=(a>>8)&255;rng_pool[rng_pptr++]^=(a>>16)&255;rng_pool[rng_pptr++]^=(a>>24)&255;if(rng_pptr>=rng_psize){rng_pptr-=rng_psize}}function rng_seed_time(){rng_seed_int(new Date().getTime())}if(rng_pool==null){rng_pool=[];rng_pptr=0;var t;try{if(window.crypto&&window.crypto.getRandomValues){var ua=new Uint8Array(32);window.crypto.getRandomValues(ua);for(t=0;t<32;++t){rng_pool[rng_pptr++]=ua[t]}}else{if(window.msCrypto&&window.msCrypto.getRandomValues){var ua=new Uint8Array(32);window.msCrypto.getRandomValues(ua);for(t=0;t<32;++t){rng_pool[rng_pptr++]=ua[t]}}else{if(window.crypto&&window.crypto.random){var z=window.crypto.random(32);for(t=0;t<z.length;++t){rng_pool[rng_pptr++]=z.charCodeAt(t)&255}}}}}catch(e){}while(rng_pptr<rng_psize){t=Math.floor(65536*Math.random());rng_pool[rng_pptr++]=t>>>8;rng_pool[rng_pptr++]=t&255}rng_pptr=0;rng_seed_time()}function rng_get_byte(){if(rng_state==null){rng_seed_time();rng_state=prng_newstate();rng_state.init(rng_pool);for(rng_pptr=0;rng_pptr<rng_pool.length;++rng_pptr){rng_pool[rng_pptr]=0}rng_pptr=0}return rng_state.next()}function rng_get_bytes(b){var a;for(a=0;a<b.length;++a){b[a]=rng_get_byte()}}function SecureRandom(){}SecureRandom.prototype.nextBytes=rng_get_bytes;

    /* rsa.js */
    function parseBigInt(b,a){return new BigInteger(b,a)}function pkcs1pad2(c,g){if(g<c.length+11){alert("Message too long for RSA");return null}var f=new Array();var e=c.length-1;while(e>=0&&g>0){f[--g]=c[e--]}f[--g]=0;var d=new SecureRandom();var a=new Array();while(g>2){a[0]=0;while(a[0]==0){d.nextBytes(a)}f[--g]=a[0]}f[--g]=2;f[--g]=0;return new BigInteger(f)}function RSAKey(){this.n=null;this.e=0;this.d=null;this.p=null;this.q=null;this.dmp1=null;this.dmq1=null;this.coeff=null}function RSASetPublic(b,a){if(b!=null&&a!=null&&b.length>0&&a.length>0){this.n=parseBigInt(b,16);this.e=parseInt(a,16)}else{alert("Invalid RSA public key")}}function RSADoPublic(a){return a.modPowInt(this.e,this.n)}function RSAEncrypt(b){var a=pkcs1pad2(b,(this.n.bitLength()+7)>>3);if(a==null){return null}var e=this.doPublic(a);if(e==null){return null}var d=e.toString(16);if((d.length&1)==0){return d}else{return"0"+d}}function RSAEncryptB64(a){var b=this.encrypt(a);if(b){return hex2b64(b)}else{return null}}RSAKey.prototype.doPublic=RSADoPublic;RSAKey.prototype.setPublic=RSASetPublic;RSAKey.prototype.encrypt=RSAEncrypt;RSAKey.prototype.encrypt_b64=RSAEncryptB64;

    /* sjcl.js */
    "use strict";function q(b){throw b}var t=void 0,u=!1;var sjcl={cipher:{},hash:{},keyexchange:{},mode:{},misc:{},codec:{},exception:{corrupt:function(b){this.toString=function(){return"CORRUPT: "+this.message};this.message=b},invalid:function(b){this.toString=function(){return"INVALID: "+this.message};this.message=b},bug:function(b){this.toString=function(){return"BUG: "+this.message};this.message=b},notReady:function(b){this.toString=function(){return"NOT READY: "+this.message};this.message=b}}};"undefined"!==typeof module&&module.exports&&(module.exports=sjcl);"function"===typeof define&&define([],function(){return sjcl});sjcl.cipher.aes=function(j){this.k[0][0][0]||this.D();var i,p,o,n,m=this.k[0][4],l=this.k[1];i=j.length;var k=1;4!==i&&(6!==i&&8!==i)&&q(new sjcl.exception.invalid("invalid aes key size"));this.b=[o=j.slice(0),n=[]];for(j=i;j<4*i+28;j++){p=o[j-1];if(0===j%i||8===i&&4===j%i){p=m[p>>>24]<<24^m[p>>16&255]<<16^m[p>>8&255]<<8^m[p&255],0===j%i&&(p=p<<8^p>>>24^k<<24,k=k<<1^283*(k>>7))}o[j]=o[j-i]^p}for(i=0;j;i++,j--){p=o[i&3?j:j-4],n[i]=4>=j||4>i?p:l[0][m[p>>>24]]^l[1][m[p>>16&255]]^l[2][m[p>>8&255]]^l[3][m[p&255]]}};sjcl.cipher.aes.prototype={encrypt:function(b){return y(this,b,0)},decrypt:function(b){return y(this,b,1)},k:[[[],[],[],[],[]],[[],[],[],[],[]]],D:function(){var R=this.k[0],Q=this.k[1],P=R[4],O=Q[4],N,x,w,v=[],r=[],s,j,o,i;for(N=0;256>N;N++){r[(v[N]=N<<1^283*(N>>7))^N]=N}for(x=w=0;!P[x];x^=s||1,w=r[w]||1){o=w^w<<1^w<<2^w<<3^w<<4;o=o>>8^o&255^99;P[x]=o;O[o]=x;j=v[N=v[s=v[x]]];i=16843009*j^65537*N^257*s^16843008*x;j=257*v[o]^16843008*o;for(N=0;4>N;N++){R[N][x]=j=j<<24^j>>>8,Q[N][o]=i=i<<24^i>>>8}}for(N=0;5>N;N++){R[N]=R[N].slice(0),Q[N]=Q[N].slice(0)}}};function y(ab,aa,Z){4!==aa.length&&q(new sjcl.exception.invalid("invalid aes block size"));var Y=ab.b[Z],X=aa[0]^Y[0],W=aa[Z?3:1]^Y[1],V=aa[2]^Y[2];aa=aa[Z?1:3]^Y[3];var U,S,T,Q=Y.length/4-2,R,P=4,N=[0,0,0,0];U=ab.k[Z];ab=U[0];var O=U[1],o=U[2],j=U[3],i=U[4];for(R=0;R<Q;R++){U=ab[X>>>24]^O[W>>16&255]^o[V>>8&255]^j[aa&255]^Y[P],S=ab[W>>>24]^O[V>>16&255]^o[aa>>8&255]^j[X&255]^Y[P+1],T=ab[V>>>24]^O[aa>>16&255]^o[X>>8&255]^j[W&255]^Y[P+2],aa=ab[aa>>>24]^O[X>>16&255]^o[W>>8&255]^j[V&255]^Y[P+3],P+=4,X=U,W=S,V=T}for(R=0;4>R;R++){N[Z?3&-R:R]=i[X>>>24]<<24^i[W>>16&255]<<16^i[V>>8&255]<<8^i[aa&255]^Y[P++],U=X,X=W,W=V,V=aa,aa=U}return N}sjcl.bitArray={bitSlice:function(e,d,f){e=sjcl.bitArray.P(e.slice(d/32),32-(d&31)).slice(1);return f===t?e:sjcl.bitArray.clamp(e,f-d)},extract:function(f,e,h){var g=Math.floor(-e-h&31);return((e+h-1^e)&-32?f[e/32|0]<<32-g^f[e/32+1|0]>>>g:f[e/32|0]>>>g)&(1<<h)-1},concat:function(f,e){if(0===f.length||0===e.length){return f.concat(e)}var h=f[f.length-1],g=sjcl.bitArray.getPartial(h);return 32===g?f.concat(e):sjcl.bitArray.P(e,g,h|0,f.slice(0,f.length-1))},bitLength:function(d){var c=d.length;return 0===c?0:32*(c-1)+sjcl.bitArray.getPartial(d[c-1])},clamp:function(e,d){if(32*e.length<d){return e}e=e.slice(0,Math.ceil(d/32));var f=e.length;d&=31;0<f&&d&&(e[f-1]=sjcl.bitArray.partial(d,e[f-1]&2147483648>>d-1,1));return e},partial:function(e,d,f){return 32===e?d:(f?d|0:d<<32-e)+1099511627776*e},getPartial:function(b){return Math.round(b/1099511627776)||32},equal:function(f,e){if(sjcl.bitArray.bitLength(f)!==sjcl.bitArray.bitLength(e)){return u}var h=0,g;for(g=0;g<f.length;g++){h|=f[g]^e[g]}return 0===h},P:function(g,f,j,i){var h;h=0;for(i===t&&(i=[]);32<=f;f-=32){i.push(j),j=0}if(0===f){return i.concat(g)}for(h=0;h<g.length;h++){i.push(j|g[h]>>>f),j=g[h]<<32-f}h=g.length?g[g.length-1]:0;g=sjcl.bitArray.getPartial(h);i.push(sjcl.bitArray.partial(f+g&31,32<f+g?j:i.pop(),1));return i},l:function(d,c){return[d[0]^c[0],d[1]^c[1],d[2]^c[2],d[3]^c[3]]},byteswapM:function(e){var d,f;for(d=0;d<e.length;++d){f=e[d],e[d]=f>>>24|f>>>8&65280|(f&65280)<<8|f<<24}return e}};sjcl.codec.utf8String={fromBits:function(g){var f="",j=sjcl.bitArray.bitLength(g),i,h;for(i=0;i<j/8;i++){0===(i&3)&&(h=g[i/4]),f+=String.fromCharCode(h>>>24),h<<=8}return decodeURIComponent(escape(f))},toBits:function(f){f=unescape(encodeURIComponent(f));var e=[],h,g=0;for(h=0;h<f.length;h++){g=g<<8|f.charCodeAt(h),3===(h&3)&&(e.push(g),g=0)}h&3&&e.push(sjcl.bitArray.partial(8*(h&3),g));return e}};sjcl.codec.hex={fromBits:function(e){var d="",f;for(f=0;f<e.length;f++){d+=((e[f]|0)+263882790666240).toString(16).substr(4)}return d.substr(0,sjcl.bitArray.bitLength(e)/4)},toBits:function(f){var e,h=[],g;f=f.replace(/\s|0x/g,"");g=f.length;f+="00000000";for(e=0;e<f.length;e+=8){h.push(parseInt(f.substr(e,8),16)^0)}return sjcl.bitArray.clamp(h,4*g)}};sjcl.codec.base64={J:"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",fromBits:function(j,i,p){var o="",n=0,m=sjcl.codec.base64.J,l=0,k=sjcl.bitArray.bitLength(j);p&&(m=m.substr(0,62)+"-_");for(p=0;6*o.length<k;){o+=m.charAt((l^j[p]>>>n)>>>26),6>n?(l=j[p]<<6-n,n+=26,p++):(l<<=6,n-=6)}for(;o.length&3&&!i;){o+="="}return o},toBits:function(j,i){j=j.replace(/\s|=/g,"");var p=[],o,n=0,m=sjcl.codec.base64.J,l=0,k;i&&(m=m.substr(0,62)+"-_");for(o=0;o<j.length;o++){k=m.indexOf(j.charAt(o)),0>k&&q(new sjcl.exception.invalid("this isn't base64!")),26<n?(n-=26,p.push(l^k>>>n),l=k<<32-n):(n+=6,l^=k<<32-n)}n&56&&p.push(sjcl.bitArray.partial(n&56,l,1));return p}};sjcl.codec.base64url={fromBits:function(b){return sjcl.codec.base64.fromBits(b,1,1)},toBits:function(b){return sjcl.codec.base64.toBits(b,1)}};sjcl.hash.sha256=function(b){this.b[0]||this.D();b?(this.r=b.r.slice(0),this.o=b.o.slice(0),this.h=b.h):this.reset()};sjcl.hash.sha256.hash=function(b){return(new sjcl.hash.sha256).update(b).finalize()};sjcl.hash.sha256.prototype={blockSize:512,reset:function(){this.r=this.N.slice(0);this.o=[];this.h=0;return this},update:function(e){"string"===typeof e&&(e=sjcl.codec.utf8String.toBits(e));var d,f=this.o=sjcl.bitArray.concat(this.o,e);d=this.h;e=this.h=d+sjcl.bitArray.bitLength(e);for(d=512+d&-512;d<=e;d+=512){z(this,f.splice(0,16))}return this},finalize:function(){var e,d=this.o,f=this.r,d=sjcl.bitArray.concat(d,[sjcl.bitArray.partial(1,1)]);for(e=d.length+2;e&15;e++){d.push(0)}d.push(Math.floor(this.h/4294967296));for(d.push(this.h|0);d.length;){z(this,d.splice(0,16))}this.reset();return f},N:[],b:[],D:function(){function f(b){return 4294967296*(b-Math.floor(b))|0}var e=0,h=2,g;f:for(;64>e;h++){for(g=2;g*g<=h;g++){if(0===h%g){continue f}}8>e&&(this.N[e]=f(Math.pow(h,0.5)));this.b[e]=f(Math.pow(h,1/3));e++}}};function z(V,U){var T,S,R,Q=U.slice(0),P=V.r,O=V.b,x=P[0],N=P[1],o=P[2],w=P[3],j=P[4],X=P[5],i=P[6],W=P[7];for(T=0;64>T;T++){16>T?S=Q[T]:(S=Q[T+1&15],R=Q[T+14&15],S=Q[T&15]=(S>>>7^S>>>18^S>>>3^S<<25^S<<14)+(R>>>17^R>>>19^R>>>10^R<<15^R<<13)+Q[T&15]+Q[T+9&15]|0),S=S+W+(j>>>6^j>>>11^j>>>25^j<<26^j<<21^j<<7)+(i^j&(X^i))+O[T],W=i,i=X,X=j,j=w+S|0,w=o,o=N,N=x,x=S+(N&o^w&(N^o))+(N>>>2^N>>>13^N>>>22^N<<30^N<<19^N<<10)|0}P[0]=P[0]+x|0;P[1]=P[1]+N|0;P[2]=P[2]+o|0;P[3]=P[3]+w|0;P[4]=P[4]+j|0;P[5]=P[5]+X|0;P[6]=P[6]+i|0;P[7]=P[7]+W|0}sjcl.mode.ccm={name:"ccm",encrypt:function(w,v,s,r,p){var o,n=v.slice(0),m=sjcl.bitArray,i=m.bitLength(s)/8,j=m.bitLength(n)/8;p=p||64;r=r||[];7>i&&q(new sjcl.exception.invalid("ccm: iv must be at least 7 bytes"));for(o=2;4>o&&j>>>8*o;o++){}o<15-i&&(o=15-i);s=m.clamp(s,8*(15-o));v=sjcl.mode.ccm.L(w,v,s,r,p,o);n=sjcl.mode.ccm.p(w,n,s,v,p,o);return m.concat(n.data,n.tag)},decrypt:function(w,v,s,r,p){p=p||64;r=r||[];var o=sjcl.bitArray,n=o.bitLength(s)/8,m=o.bitLength(v),i=o.clamp(v,m-p),j=o.bitSlice(v,m-p),m=(m-p)/8;7>n&&q(new sjcl.exception.invalid("ccm: iv must be at least 7 bytes"));for(v=2;4>v&&m>>>8*v;v++){}v<15-n&&(v=15-n);s=o.clamp(s,8*(15-v));i=sjcl.mode.ccm.p(w,i,s,j,p,v);w=sjcl.mode.ccm.L(w,i.data,s,r,p,v);o.equal(i.tag,w)||q(new sjcl.exception.corrupt("ccm: tag doesn't match"));return i.data},L:function(s,r,p,o,n,m){var k=[],j=sjcl.bitArray,i=j.l;n/=8;(n%2||4>n||16<n)&&q(new sjcl.exception.invalid("ccm: invalid tag length"));(4294967295<o.length||4294967295<r.length)&&q(new sjcl.exception.bug("ccm: can't deal with 4GiB or more data"));m=[j.partial(8,(o.length?64:0)|n-2<<2|m-1)];m=j.concat(m,p);m[3]|=j.bitLength(r)/8;m=s.encrypt(m);if(o.length){p=j.bitLength(o)/8;65279>=p?k=[j.partial(16,p)]:4294967295>=p&&(k=j.concat([j.partial(16,65534)],[p]));k=j.concat(k,o);for(o=0;o<k.length;o+=4){m=s.encrypt(i(m,k.slice(o,o+4).concat([0,0,0])))}}for(o=0;o<r.length;o+=4){m=s.encrypt(i(m,r.slice(o,o+4).concat([0,0,0])))}return j.clamp(m,8*n)},p:function(w,v,s,r,p,o){var n,m=sjcl.bitArray;n=m.l;var i=v.length,j=m.bitLength(v);s=m.concat([m.partial(8,o-1)],s).concat([0,0,0]).slice(0,4);r=m.bitSlice(n(r,w.encrypt(s)),0,p);if(!i){return{tag:r,data:[]}}for(n=0;n<i;n+=4){s[3]++,p=w.encrypt(s),v[n]^=p[0],v[n+1]^=p[1],v[n+2]^=p[2],v[n+3]^=p[3]}return{tag:r,data:m.clamp(v,j)}}};sjcl.mode.ocb2={name:"ocb2",encrypt:function(R,Q,P,O,N,x){128!==sjcl.bitArray.bitLength(P)&&q(new sjcl.exception.invalid("ocb iv must be 128 bits"));var w,v=sjcl.mode.ocb2.H,r=sjcl.bitArray,s=r.l,j=[0,0,0,0];P=v(R.encrypt(P));var o,i=[];O=O||[];N=N||64;for(w=0;w+4<Q.length;w+=4){o=Q.slice(w,w+4),j=s(j,o),i=i.concat(s(P,R.encrypt(s(P,o)))),P=v(P)}o=Q.slice(w);Q=r.bitLength(o);w=R.encrypt(s(P,[0,0,0,Q]));o=r.clamp(s(o.concat([0,0,0]),w),Q);j=s(j,s(o.concat([0,0,0]),w));j=R.encrypt(s(j,s(P,v(P))));O.length&&(j=s(j,x?O:sjcl.mode.ocb2.pmac(R,O)));return i.concat(r.concat(o,r.clamp(j,N)))},decrypt:function(U,T,S,R,Q,P){128!==sjcl.bitArray.bitLength(S)&&q(new sjcl.exception.invalid("ocb iv must be 128 bits"));Q=Q||64;var O=sjcl.mode.ocb2.H,N=sjcl.bitArray,w=N.l,x=[0,0,0,0],o=O(U.encrypt(S)),v,j,V=sjcl.bitArray.bitLength(T)-Q,i=[];R=R||[];for(S=0;S+4<V/32;S+=4){v=w(o,U.decrypt(w(o,T.slice(S,S+4)))),x=w(x,v),i=i.concat(v),o=O(o)}j=V-32*S;v=U.encrypt(w(o,[0,0,0,j]));v=w(v,N.clamp(T.slice(S),j).concat([0,0,0]));x=w(x,v);x=U.encrypt(w(x,w(o,O(o))));R.length&&(x=w(x,P?R:sjcl.mode.ocb2.pmac(U,R)));N.equal(N.clamp(x,Q),N.bitSlice(T,V))||q(new sjcl.exception.corrupt("ocb: tag doesn't match"));return i.concat(N.clamp(v,j))},pmac:function(j,i){var p,o=sjcl.mode.ocb2.H,n=sjcl.bitArray,m=n.l,l=[0,0,0,0],k=j.encrypt([0,0,0,0]),k=m(k,o(o(k)));for(p=0;p+4<i.length;p+=4){k=o(k),l=m(l,j.encrypt(m(k,i.slice(p,p+4))))}p=i.slice(p);128>n.bitLength(p)&&(k=m(k,o(k)),p=n.concat(p,[-2147483648,0,0,0]));l=m(l,p);return j.encrypt(m(o(m(k,o(k))),l))},H:function(b){return[b[0]<<1^b[1]>>>31,b[1]<<1^b[2]>>>31,b[2]<<1^b[3]>>>31,b[3]<<1^135*(b[0]>>>31)]}};sjcl.mode.gcm={name:"gcm",encrypt:function(h,g,l,k,j){var i=g.slice(0);g=sjcl.bitArray;k=k||[];h=sjcl.mode.gcm.p(!0,h,i,k,l,j||128);return g.concat(h.data,h.tag)},decrypt:function(j,i,p,o,n){var m=i.slice(0),l=sjcl.bitArray,k=l.bitLength(m);n=n||128;o=o||[];n<=k?(i=l.bitSlice(m,k-n),m=l.bitSlice(m,0,k-n)):(i=m,m=[]);j=sjcl.mode.gcm.p(u,j,m,o,p,n);l.equal(j.tag,i)||q(new sjcl.exception.corrupt("gcm: tag doesn't match"));return j.data},Z:function(j,i){var p,o,n,m,l,k=sjcl.bitArray.l;n=[0,0,0,0];m=i.slice(0);for(p=0;128>p;p++){(o=0!==(j[Math.floor(p/32)]&1<<31-p%32))&&(n=k(n,m));l=0!==(m[3]&1);for(o=3;0<o;o--){m[o]=m[o]>>>1|(m[o-1]&1)<<31}m[0]>>>=1;l&&(m[0]^=-520093696)}return n},g:function(g,f,j){var i,h=j.length;f=f.slice(0);for(i=0;i<h;i+=4){f[0]^=4294967295&j[i],f[1]^=4294967295&j[i+1],f[2]^=4294967295&j[i+2],f[3]^=4294967295&j[i+3],f=sjcl.mode.gcm.Z(f,g)}return f},p:function(U,T,S,R,Q,P){var O,N,w,x,o,v,j,V,i=sjcl.bitArray;v=S.length;j=i.bitLength(S);V=i.bitLength(R);N=i.bitLength(Q);O=T.encrypt([0,0,0,0]);96===N?(Q=Q.slice(0),Q=i.concat(Q,[1])):(Q=sjcl.mode.gcm.g(O,[0,0,0,0],Q),Q=sjcl.mode.gcm.g(O,Q,[0,0,Math.floor(N/4294967296),N&4294967295]));N=sjcl.mode.gcm.g(O,[0,0,0,0],R);o=Q.slice(0);R=N.slice(0);U||(R=sjcl.mode.gcm.g(O,N,S));for(x=0;x<v;x+=4){o[3]++,w=T.encrypt(o),S[x]^=w[0],S[x+1]^=w[1],S[x+2]^=w[2],S[x+3]^=w[3]}S=i.clamp(S,j);U&&(R=sjcl.mode.gcm.g(O,N,S));U=[Math.floor(V/4294967296),V&4294967295,Math.floor(j/4294967296),j&4294967295];R=sjcl.mode.gcm.g(O,R,U);w=T.encrypt(Q);R[0]^=w[0];R[1]^=w[1];R[2]^=w[2];R[3]^=w[3];return{tag:i.bitSlice(R,0,P),data:S}}};sjcl.misc.hmac=function(g,f){this.M=f=f||sjcl.hash.sha256;var j=[[],[]],i,h=f.prototype.blockSize/32;this.n=[new f,new f];g.length>h&&(g=f.hash(g));for(i=0;i<h;i++){j[0][i]=g[i]^909522486,j[1][i]=g[i]^1549556828}this.n[0].update(j[0]);this.n[1].update(j[1]);this.G=new f(this.n[0])};sjcl.misc.hmac.prototype.encrypt=sjcl.misc.hmac.prototype.mac=function(b){this.Q&&q(new sjcl.exception.invalid("encrypt on already updated hmac called!"));this.update(b);return this.digest(b)};sjcl.misc.hmac.prototype.reset=function(){this.G=new this.M(this.n[0]);this.Q=u};sjcl.misc.hmac.prototype.update=function(b){this.Q=!0;this.G.update(b)};sjcl.misc.hmac.prototype.digest=function(){var b=this.G.finalize(),b=(new this.M(this.n[1])).update(b).finalize();this.reset();return b};sjcl.misc.pbkdf2=function(N,x,w,v,s){w=w||1000;(0>v||0>w)&&q(sjcl.exception.invalid("invalid params to pbkdf2"));"string"===typeof N&&(N=sjcl.codec.utf8String.toBits(N));"string"===typeof x&&(x=sjcl.codec.utf8String.toBits(x));s=s||sjcl.misc.hmac;N=new s(N);var r,p,o,j,m=[],i=sjcl.bitArray;for(j=1;32*m.length<(v||1);j++){s=r=N.encrypt(i.concat(x,[j]));for(p=1;p<w;p++){r=N.encrypt(r);for(o=0;o<r.length;o++){s[o]^=r[o]}}m=m.concat(s)}v&&(m=i.clamp(m,v));return m};sjcl.prng=function(b){this.c=[new sjcl.hash.sha256];this.i=[0];this.F=0;this.s={};this.C=0;this.K={};this.O=this.d=this.j=this.W=0;this.b=[0,0,0,0,0,0,0,0];this.f=[0,0,0,0];this.A=t;this.B=b;this.q=u;this.w={progress:{},seeded:{}};this.m=this.V=0;this.t=1;this.u=2;this.S=65536;this.I=[0,48,64,96,128,192,256,384,512,768,1024];this.T=30000;this.R=80};sjcl.prng.prototype={randomWords:function(i,h){var n=[],m;m=this.isReady(h);var l;m===this.m&&q(new sjcl.exception.notReady("generator isn't seeded"));if(m&this.u){m=!(m&this.t);l=[];var k=0,j;this.O=l[0]=(new Date).valueOf()+this.T;for(j=0;16>j;j++){l.push(4294967296*Math.random()|0)}for(j=0;j<this.c.length&&!(l=l.concat(this.c[j].finalize()),k+=this.i[j],this.i[j]=0,!m&&this.F&1<<j);j++){}this.F>=1<<this.c.length&&(this.c.push(new sjcl.hash.sha256),this.i.push(0));this.d-=k;k>this.j&&(this.j=k);this.F++;this.b=sjcl.hash.sha256.hash(this.b.concat(l));this.A=new sjcl.cipher.aes(this.b);for(m=0;4>m&&!(this.f[m]=this.f[m]+1|0,this.f[m]);m++){}}for(m=0;m<i;m+=4){0===(m+1)%this.S&&A(this),l=B(this),n.push(l[0],l[1],l[2],l[3])}A(this);return n.slice(0,i)},setDefaultParanoia:function(d,c){0===d&&"Setting paranoia=0 will ruin your security; use it only for testing"!==c&&q("Setting paranoia=0 will ruin your security; use it only for testing");this.B=d},addEntropy:function(s,r,p){p=p||"user";var o,n,m=(new Date).valueOf(),k=this.s[p],j=this.isReady(),i=0;o=this.K[p];o===t&&(o=this.K[p]=this.W++);k===t&&(k=this.s[p]=0);this.s[p]=(this.s[p]+1)%this.c.length;switch(typeof s){case"number":r===t&&(r=1);this.c[k].update([o,this.C++,1,r,m,1,s|0]);break;case"object":p=Object.prototype.toString.call(s);if("[object Uint32Array]"===p){n=[];for(p=0;p<s.length;p++){n.push(s[p])}s=n}else{"[object Array]"!==p&&(i=1);for(p=0;p<s.length&&!i;p++){"number"!==typeof s[p]&&(i=1)}}if(!i){if(r===t){for(p=r=0;p<s.length;p++){for(n=s[p];0<n;){r++,n>>>=1}}}this.c[k].update([o,this.C++,2,r,m,s.length].concat(s))}break;case"string":r===t&&(r=s.length);this.c[k].update([o,this.C++,3,r,m,s.length]);this.c[k].update(s);break;default:i=1}i&&q(new sjcl.exception.bug("random: addEntropy only supports number, array of numbers or string"));this.i[k]+=r;this.d+=r;j===this.m&&(this.isReady()!==this.m&&C("seeded",Math.max(this.j,this.d)),C("progress",this.getProgress()))},isReady:function(b){b=this.I[b!==t?b:this.B];return this.j&&this.j>=b?this.i[0]>this.R&&(new Date).valueOf()>this.O?this.u|this.t:this.t:this.d>=b?this.u|this.m:this.m},getProgress:function(b){b=this.I[b?b:this.B];return this.j>=b?1:this.d>b?1:this.d/b},startCollectors:function(){this.q||(this.a={loadTimeCollector:D(this,this.aa),mouseCollector:D(this,this.ba),keyboardCollector:D(this,this.$),accelerometerCollector:D(this,this.U)},window.addEventListener?(window.addEventListener("load",this.a.loadTimeCollector,u),window.addEventListener("mousemove",this.a.mouseCollector,u),window.addEventListener("keypress",this.a.keyboardCollector,u),window.addEventListener("devicemotion",this.a.accelerometerCollector,u)):document.attachEvent?(document.attachEvent("onload",this.a.loadTimeCollector),document.attachEvent("onmousemove",this.a.mouseCollector),document.attachEvent("keypress",this.a.keyboardCollector)):q(new sjcl.exception.bug("can't attach event")),this.q=!0)},stopCollectors:function(){this.q&&(window.removeEventListener?(window.removeEventListener("load",this.a.loadTimeCollector,u),window.removeEventListener("mousemove",this.a.mouseCollector,u),window.removeEventListener("keypress",this.a.keyboardCollector,u),window.removeEventListener("devicemotion",this.a.accelerometerCollector,u)):document.detachEvent&&(document.detachEvent("onload",this.a.loadTimeCollector),document.detachEvent("onmousemove",this.a.mouseCollector),document.detachEvent("keypress",this.a.keyboardCollector)),this.q=u)},addEventListener:function(d,c){this.w[d][this.V++]=c},removeEventListener:function(h,g){var l,k,j=this.w[h],i=[];for(k in j){j.hasOwnProperty(k)&&j[k]===g&&i.push(k)}for(l=0;l<i.length;l++){k=i[l],delete j[k]}},$:function(){E(1)},ba:function(f){var e,h;try{e=f.x||f.clientX||f.offsetX||0,h=f.y||f.clientY||f.offsetY||0}catch(g){h=e=0}0!=e&&0!=h&&sjcl.random.addEntropy([e,h],2,"mouse");E(0)},aa:function(){E(2)},U:function(d){d=d.accelerationIncludingGravity.x||d.accelerationIncludingGravity.y||d.accelerationIncludingGravity.z;if(window.orientation){var c=window.orientation;"number"===typeof c&&sjcl.random.addEntropy(c,1,"accelerometer")}d&&sjcl.random.addEntropy(d,2,"accelerometer");E(0)}};function C(g,f){var j,i=sjcl.random.w[g],h=[];for(j in i){i.hasOwnProperty(j)&&h.push(i[j])}for(j=0;j<h.length;j++){h[j](f)}}function E(b){"undefined"!==typeof window&&window.performance&&"function"===typeof window.performance.now?sjcl.random.addEntropy(window.performance.now(),b,"loadtime"):sjcl.random.addEntropy((new Date).valueOf(),b,"loadtime")}function A(b){b.b=B(b).concat(B(b));b.A=new sjcl.cipher.aes(b.b)}function B(d){for(var c=0;4>c&&!(d.f[c]=d.f[c]+1|0,d.f[c]);c++){}return d.A.encrypt(d.f)}function D(d,c){return function(){c.apply(d,arguments)}}sjcl.random=new sjcl.prng(6);a:try{var F,G,H,I;if(I="undefined"!==typeof module){var J;if(J=module.exports){var K;try{K=require("crypto")}catch(L){K=null}J=(G=K)&&G.randomBytes}I=J}if(I){F=G.randomBytes(128),F=new Uint32Array((new Uint8Array(F)).buffer),sjcl.random.addEntropy(F,1024,"crypto['randomBytes']")}else{if("undefined"!==typeof window&&"undefined"!==typeof Uint32Array){H=new Uint32Array(32);if(window.crypto&&window.crypto.getRandomValues){window.crypto.getRandomValues(H)}else{if(window.msCrypto&&window.msCrypto.getRandomValues){window.msCrypto.getRandomValues(H)}else{break a}}sjcl.random.addEntropy(H,1024,"crypto['getRandomValues']")}}}catch(M){"undefined"!==typeof window&&window.console&&(console.log("There was an error collecting entropy from the browser:"),console.log(M))}sjcl.json={defaults:{v:1,iter:1000,ks:128,ts:64,mode:"ccm",adata:"",cipher:"aes"},Y:function(i,h,n,m){n=n||{};m=m||{};var l=sjcl.json,k=l.e({iv:sjcl.random.randomWords(4,0)},l.defaults),j;l.e(k,n);n=k.adata;"string"===typeof k.salt&&(k.salt=sjcl.codec.base64.toBits(k.salt));"string"===typeof k.iv&&(k.iv=sjcl.codec.base64.toBits(k.iv));(!sjcl.mode[k.mode]||!sjcl.cipher[k.cipher]||"string"===typeof i&&100>=k.iter||64!==k.ts&&96!==k.ts&&128!==k.ts||128!==k.ks&&192!==k.ks&&256!==k.ks||2>k.iv.length||4<k.iv.length)&&q(new sjcl.exception.invalid("json encrypt: invalid parameters"));"string"===typeof i?(j=sjcl.misc.cachedPbkdf2(i,k),i=j.key.slice(0,k.ks/32),k.salt=j.salt):sjcl.ecc&&i instanceof sjcl.ecc.elGamal.publicKey&&(j=i.kem(),k.kemtag=j.tag,i=j.key.slice(0,k.ks/32));"string"===typeof h&&(h=sjcl.codec.utf8String.toBits(h));"string"===typeof n&&(n=sjcl.codec.utf8String.toBits(n));j=new sjcl.cipher[k.cipher](i);l.e(m,k);m.key=i;k.ct=sjcl.mode[k.mode].encrypt(j,h,k.iv,n,k.ts);return k},encrypt:function(h,g,l,k){var j=sjcl.json,i=j.Y.apply(j,arguments);return j.encode(i)},X:function(i,h,n,m){n=n||{};m=m||{};var l=sjcl.json;h=l.e(l.e(l.e({},l.defaults),h),n,!0);var k,j;k=h.adata;"string"===typeof h.salt&&(h.salt=sjcl.codec.base64.toBits(h.salt));"string"===typeof h.iv&&(h.iv=sjcl.codec.base64.toBits(h.iv));(!sjcl.mode[h.mode]||!sjcl.cipher[h.cipher]||"string"===typeof i&&100>=h.iter||64!==h.ts&&96!==h.ts&&128!==h.ts||128!==h.ks&&192!==h.ks&&256!==h.ks||!h.iv||2>h.iv.length||4<h.iv.length)&&q(new sjcl.exception.invalid("json decrypt: invalid parameters"));"string"===typeof i?(j=sjcl.misc.cachedPbkdf2(i,h),i=j.key.slice(0,h.ks/32),h.salt=j.salt):sjcl.ecc&&i instanceof sjcl.ecc.elGamal.secretKey&&(i=i.unkem(sjcl.codec.base64.toBits(h.kemtag)).slice(0,h.ks/32));"string"===typeof k&&(k=sjcl.codec.utf8String.toBits(k));j=new sjcl.cipher[h.cipher](i);k=sjcl.mode[h.mode].decrypt(j,h.ct,h.iv,k,h.ts);l.e(m,h);m.key=i;return 1===n.raw?k:sjcl.codec.utf8String.fromBits(k)},decrypt:function(g,f,j,i){var h=sjcl.json;return h.X(g,h.decode(f),j,i)},encode:function(f){var e,h="{",g="";for(e in f){if(f.hasOwnProperty(e)){switch(e.match(/^[a-z0-9]+$/i)||q(new sjcl.exception.invalid("json encode: invalid property name")),h+=g+'"'+e+'":',g=",",typeof f[e]){case"number":case"boolean":h+=f[e];break;case"string":h+='"'+escape(f[e])+'"';break;case"object":h+='"'+sjcl.codec.base64.fromBits(f[e],0)+'"';break;default:q(new sjcl.exception.bug("json encode: unsupported type"))}}}return h+"}"},decode:function(f){f=f.replace(/\s/g,"");f.match(/^\{.*\}$/)||q(new sjcl.exception.invalid("json decode: this isn't json!"));f=f.replace(/^\{|\}$/g,"").split(/,/);var e={},h,g;for(h=0;h<f.length;h++){(g=f[h].match(/^(?:(["']?)([a-z][a-z0-9]*)\1):(?:(\d+)|"([a-z0-9+\/%*_.@=\-]*)")$/i))||q(new sjcl.exception.invalid("json decode: this isn't json!")),e[g[2]]=g[3]?parseInt(g[3],10):g[2].match(/^(ct|salt|iv)$/)?sjcl.codec.base64.toBits(g[4]):unescape(g[4])}return e},e:function(f,e,h){f===t&&(f={});if(e===t){return f}for(var g in e){e.hasOwnProperty(g)&&(h&&(f[g]!==t&&f[g]!==e[g])&&q(new sjcl.exception.invalid("required parameter overridden")),f[g]=e[g])}return f},ea:function(f,e){var h={},g;for(g in f){f.hasOwnProperty(g)&&f[g]!==e[g]&&(h[g]=f[g])}return h},da:function(f,e){var h={},g;for(g=0;g<e.length;g++){f[e[g]]!==t&&(h[e[g]]=f[e[g]])}return h}};sjcl.encrypt=sjcl.json.encrypt;sjcl.decrypt=sjcl.json.decrypt;sjcl.misc.ca={};sjcl.misc.cachedPbkdf2=function(f,e){var h=sjcl.misc.ca,g;e=e||{};g=e.iter||1000;h=h[f]=h[f]||{};g=h[g]=h[g]||{firstSalt:e.salt&&e.salt.length?e.salt.slice(0):sjcl.random.randomWords(2,0)};h=e.salt===t?g.firstSalt:e.salt;g[h]=g[h]||sjcl.misc.pbkdf2(f,h,e.iter);return{key:g[h].slice(0),salt:h.slice(0)}};

    /* sjcl.patch.js */
    (function(a){var b=a.codec.bytes=a.codec.bytes||{};b.fromBits=b.fromBits||function(c){var d=[],g=a.bitArray.bitLength(c),f,e;for(f=0;f<g/8;f++){if((f&3)===0){e=c[f/4]}d.push(e>>>24);e<<=8}return d};b.toBits=b.toBits||function(c){var d=[],f,e=0;for(f=0;f<c.length;f++){e=e<<8|c[f];if((f&3)===3){d.push(e);e=0}}if(f&3){d.push(a.bitArray.partial(8*(f&3),e))}return d}}(sjcl));

    /* adyen.eventlog.js */
    var evLog;(function(){function a(c,d,e,b){if(typeof c.addEventListener==="function"){c.addEventListener(d,e,b)}else{if(c.attachEvent){c.attachEvent("on"+d,e)}else{throw new Error(encrypt.errors.UNABLETOBIND+": Unable to bind "+d+"-event")}}}evLog=evLog||(function(){var b={};return function(f,e,c){if(f==="bind"){evLog(c+"Bind");a(e,"change",function(){evLog(c+"FieldChangeCount");evLog("log",c,"ch")},true);a(e,"click",function(){evLog(c+"FieldClickCount");evLog("log",c,"cl")},true);a(e,"focus",function(){evLog(c+"FieldFocusCount");evLog("log",c,"fo")},true);a(e,"blur",function(){evLog(c+"FieldBlurCount");evLog("log",c,"bl")},true);a(e,"keyup",function(h){if(h.keyCode==16){evLog("log",c,"Su")}else{if(h.keyCode==17){evLog("log",c,"Cu")}else{if(h.keyCode==18){evLog("log",c,"Au")}}}});a(e,"keydown",function(h){evLog(c+"FieldKeyCount");switch(h&&h.keyCode){case 8:evLog("log",c,"Kb");break;case 16:evLog("log",c,"Sd");break;case 17:evLog("log",c,"Cd");break;case 18:evLog("log",c,"Ad");break;case 37:evLog("log",c,"Kl");break;case 39:evLog("log",c,"Kr");break;case 46:evLog("log",c,"Kd");break;case 32:evLog("log",c,"Ks");break;default:if(h.keyCode>=48&&h.keyCode<=57||h.keyCode>=96&&h.keyCode<=105){evLog("log",c,"KN")}else{if(h.keyCode>=65&&h.keyCode<=90){evLog("log",c,"KL")}}break}},true);return}if(f==="set"){b[e]=c;return}if(f==="log"){var g=e+"FieldLog";if(!b.hasOwnProperty(g)){b[g]=c}else{b[g]+=","+c}if(b[g].length>1500){b[g]=b[g].substring(b[g].length-1500)}return}if(f==="extend"){for(var d in b){if(d==="number"||d==="expiryMonth"||d==="expiryYear"||d==="generationtime"||d==="holderName"||d==="cvc"){continue}if(b.hasOwnProperty(d)){e[d]=""+b[d]}}return}if(!b.hasOwnProperty(f)){b[f]=1}else{b[f]++}}})();if(window&&(window.attachEvent||window.addEventListener)){a(window,"focus",function(){evLog("activate")});a(window,"blur",function(){evLog("deactivate")})}}());

    /* adyen */
    var adyen = root.adyen = root.adyen || {};

    var encrypt = adyen.encrypt = adyen.encrypt || {
            createEncryptedForm : function ( form, key, options ) {
                return new EncryptedForm( form, key, options );
            },
            createEncryption : function ( key, options ) {
                return new Encryption( key, options );
            }
        };

    if (typeof fnDefine === 'function' && fnDefine.amd) {
        fnDefine('adyen/encrypt', [], function() {
            return encrypt;
        });
    }

    encrypt.errors = encrypt.errors || {};
    encrypt.errors.UNABLETOBIND = 'CSEB01';

    function addEvent ( element, event, callback, capture ) {
        if ( typeof element.addEventListener === 'function' ) {
            element.addEventListener( event, callback, capture );
        } else if ( element.attachEvent ) {
            element.attachEvent( 'on' + event, callback );
        } else {
            throw new Error( encrypt.errors.UNABLETOBIND + ": Unable to bind " + event + "-event" );
        }
    }

    function hasClass ( elem, className ) {
        return elem && new RegExp( ' ' + className + ' ' ).test( ' ' + ( elem.className || '' ) + ' ' );
    }

    function addClass ( elem, className ) {
        if ( !elem ) {
            return;
        }
        if ( !hasClass( elem, className ) ) {
            elem.className += ' ' + className;
        }
    }

    function removeClass ( elem, className ) {
        if ( !elem ) {
            return;
        }
        var newClass = ' ' + elem.className.replace( /[\t\r\n]/g, ' ' ) + ' ';
        if ( hasClass( elem, className ) ) {
            while ( newClass.indexOf( ' ' + className + ' ' ) >= 0 ) {
                newClass = newClass.replace( ' ' + className + ' ', ' ' );
            }
            elem.className = newClass.replace( /^\s+|\s+$/g, '' );
        }
    }

    function getAttribute ( node, attribute, defaultValue ) {
        if ( node && node.getAttribute ) {
            return node.getAttribute( attribute ) || defaultValue;
        } else {
            return defaultValue;
        }
    }


    encrypt.version = '0_1_14';


    /*
     * Compatibility JavaScript older than 1.8.5 (IE8, IE7)
     * 
     * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Function/bind
     */
    if ( !Function.prototype.bind ) {
        Function.prototype.bind = function ( oThis ) {
            if ( typeof this !== "function" ) {
                // closest thing possible to the ECMAScript 5 internal
                // IsCallable function
                throw new TypeError( "Function.prototype.bind - what is trying to be bound is not callable" );
            }
            var aArgs = Array.prototype.slice.call( arguments, 1 ), fToBind = this, fNOP = function () {
            }, fBound = function () {
                return fToBind.apply( this instanceof fNOP && oThis ? this : oThis, aArgs.concat( Array.prototype.slice.call( arguments ) ) );
            };

            fNOP.prototype = this.prototype;
            fBound.prototype = new fNOP();

            return fBound;
        };
    }

    /*
     * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date/toISOString
     */
    if ( !Date.prototype.toISOString ) {
        ( function () {

            function pad ( number ) {
                if ( number < 10 ) {
                    return '0' + number;
                }
                return number;
            }

            Date.prototype.toISOString = function () {
                return this.getUTCFullYear() + '-' + pad( this.getUTCMonth() + 1 ) + '-' + pad( this.getUTCDate() ) + 'T' + pad( this.getUTCHours() ) + ':' + pad( this.getUTCMinutes() ) + ':' + pad( this.getUTCSeconds() ) + '.'
                    + ( this.getUTCMilliseconds() / 1000 ).toFixed( 3 ).slice( 2, 5 ) + 'Z';
            };

        }() );
    }



    // Validations
    var validations = {};

    /***************************************************************************
     * boolean luhnCheck([String CardNumber]) return true if CardNumber pass the
     * luhn check else return false. Reference:
     * http://www.ling.nwu.edu/~sburke/pub/luhn_lib.pl \
     **************************************************************************/
    validations.luhnCheck = (function() {

        var luhnCache = {};

        return function () {
            var argv = arguments;
            var argc = arguments.length;

            var CardNumber = argc > 0 ? argv[ 0 ] : this.cardnumber;

            if ( isNaN( parseInt( CardNumber, 10 ) ) ) {
                return false;
            }

            var no_digit = CardNumber.length;
            var oddoeven = no_digit & 1;
            var sum = 0;

            if (typeof luhnCache[CardNumber] === "undefined") {
                if (no_digit >= 14) {
                    evLog('luhnCount');
                }

                for ( var count = 0; count < no_digit; count++ ) {
                    var digit = parseInt( CardNumber.charAt( count ), 10 );
                    if ( ! ( ( count & 1 ) ^ oddoeven ) ) {
                        digit *= 2;
                        if ( digit > 9 )
                            digit -= 9;
                    }
                    sum += digit;
                }

                if ( sum % 10 === 0 ) {
                    evLog("luhnOkCount");
                    luhnCache[CardNumber] = true;
                } else {
                    evLog("luhnFailCount");
                    luhnCache[CardNumber] = false;
                }
            }

            var luhnCacheTries = 0;

            for (var i in luhnCache) {
                if (luhnCache.hasOwnProperty(i) && i.length === no_digit) {
                    luhnCacheTries++;
                }
            }

            evLog("set", "luhnSameLengthCount", luhnCacheTries );

            return luhnCache[CardNumber];
        };
    })();

    validations.numberCheck = function ( val ) {
        return validations.luhnCheck(val);
    };

    validations.cvcCheck = function ( val ) {
        return (val && val.match && val.match( /^\d{3,4}$/ )) ? true : false;
    };

    validations.yearCheck = function ( val ) {
        return (val && val.match && val.match( /^\d{4}$/ )) ? true : false;
    };

    validations.monthCheck = function ( val ) {

        var myVal = (val || '').replace(/^0(\d)$/, '$1');

        return (myVal.match( /^([1-9]|10|11|12)$/ ) && parseInt( myVal, 10 ) >= 1 && parseInt( myVal, 10 ) <= 12) ? true : false;
    };

    validations.holderNameCheck = function ( val ) {
        return (val && val.match && val.match(/\S/)) ? true : false;
    };

    var Encryption = function ( key, options ) {
        try {
            sjcl.random.startCollectors();
        } catch ( e ) {
            // what to do?
        }

        this.key = key;

        this.options = options || {};

        // Defaults
        if ( typeof this.options.numberIgnoreNonNumeric === "undefined" ) {
            this.options.numberIgnoreNonNumeric = true;
        }

        if ( typeof this.options.cvcIgnoreFornumber !== "undefined" ) {
            delete this.options.cvcIgnoreFornumber;
        }

        if ( typeof this.options.cvcIgnoreBins === "string" ) {
            var binsToIgnore = [];
            this.options.cvcIgnoreBins.replace(/\d+/g, function(m) {
                if (m.length > 0 && !isNaN(parseInt(m, 10))) {
                    binsToIgnore.push(m);
                }
                return m;
            });

            if (binsToIgnore.length > 0) {
                this.options.cvcIgnoreFornumber = new RegExp("^\\s*(" + binsToIgnore.join("|") + ")");
            }

        } else if (typeof this.options.cvcIgnoreBins !== "undefined" ) {
            delete this.options.cvcIgnoreBins;
        }

        evLog("initializeCount");
    };

    /*
     * Creates an RSA key based on the public key.
     * 
     * @returns rsa {RSAKey} An RSAKey based on the public key provided.
     * 
     */
    Encryption.prototype.createRSAKey = function () {

        var k = this.key.split( '|' );

        if ( k.length != 2 ) {
            throw 'Malformed public key';
        }

        var exp = k[ 0 ];
        var mod = k[ 1 ];

        // would be better to put it in a package.
        var rsa = new RSAKey();
        rsa.setPublic( mod, exp );

        return rsa;

    };

    /*
     * Creates an AES key.
     * 
     * @returns aes {Object} An AESKey with encryption methods.
     * 
     */
    Encryption.prototype.createAESKey = function () {
        return new AESKey();
    };

    /*
     * Encrypts data
     * 
     * @return data {String} The data in the form as encrypted and serialized
     * JSON.
     * 
     */

    Encryption.prototype.encrypt = function ( data ) {

        var rsa, aes, cipher, keybytes, encrypted, prefix, validationObject = {
            number : data.number || '',
            cvc : data.cvc || '',
            month: data.expiryMonth || '',
            year : data.expiryYear || ''
        };

        if ( this.options.enableValidations !== false && this.validate(validationObject).valid === false) {

            return false;

        }

        evLog('extend', data);

        rsa = this.createRSAKey();
        aes = this.createAESKey();

        cipher = aes.encrypt( JSON.stringify( data ) );
        keybytes = sjcl.codec.bytes.fromBits( aes.key );
        encrypted = rsa.encrypt_b64( keybytes );
        prefix = 'adyenjs_' + encrypt.version + '$';

        return [ prefix, encrypted, '$', cipher ].join( '' );
    };

    Encryption.prototype.validate = function ( data ) {
        var result = {};

        result.valid = true;

        if ( typeof data === "object" ) {
            for ( var field in data ) {
                if ( data.hasOwnProperty( field ) ) {

                    var val = data[ field ];

                    if ( this.options[ field + 'IgnoreNonNumeric' ] ) {
                        val = val.replace( /\D/g, '' );
                    }

                    var shouldIgnore = false;

                    for ( var relatedField in data ) {
                        if ( data.hasOwnProperty(relatedField) ) {

                            var possibleOption = this.options[field + 'IgnoreFor' + relatedField] ;

                            if ( possibleOption && data[relatedField].match(possibleOption)) {
                                shouldIgnore = true;
                            }
                        }
                    }

                    if (shouldIgnore) {
                        result[field] = true;
                        result.valid = result.valid && result[field];
                        continue;
                    }

                    switch ( field ) {
                        case 'number':
                            result.number = validations.luhnCheck( val );
                            result.luhn = result.number;
                            result.valid = result.valid && result.number;
                            break;
                        case 'expiryYear':
                        case 'year':
                            result.year = validations.yearCheck( val );
                            result.expiryYear = result.year;
                            result.valid = result.valid && result.year;
                            break;
                        case 'cvc':
                            result.cvc = validations.cvcCheck( val );
                            result.valid = result.valid && result.cvc;
                            break;
                        case 'expiryMonth':
                        case 'month':
                            result.month = validations.monthCheck( val );
                            result.expiryMonth = result.month;
                            result.valid = result.valid && result.month;
                            break;
                        case 'holderName':
                            result.holderName = validations.holderNameCheck(val)
                            result.valid = result.valid && result.holderName;
                            break;
                        default:
                            result.unknown = result.unknown || [];
                            result.unknown.push( field );
                            result.valid = false;
                    }

                }
            }
        } else {
            result.valid = false;
        }

        return result;
    };


    validations.createChangeHandler = function ( cse, field, allowEmpty ) {
        return function ( ev ) {
            var node = ev.target || ev.srcElement, val = ( node || {} ).value || '';

            var isInitializing = (typeof ev.isInitializing === "boolean" && ev.isInitializing);

            if ( node.options && typeof node.selectedIndex !== 'undefined' ) {
                val = node.options[ node.selectedIndex ].value;
            }

            if ( cse.options[ field + 'IgnoreNonNumeric' ] ) {
                val = val.replace( /\D/g, '' );
            }

            // Prepare to run it through the encryption engine's validation. No longer use double validation
            var fieldData = cse.toJSON(cse.getEncryptedFields(cse.element));

            var validationData = {
                year : fieldData.expiryYear,
                month: fieldData.expiryMonth,
                number : fieldData.number,
                cvc : fieldData.cvc,
                holderName : fieldData.holderName
            };

            var validationResult = cse.encryption.validate(validationData);

            if ( validationResult[field] ) {
                cse.validity[ field ] = true;
                removeClass( node, 'invalid-' + field );
                addClass( node, 'valid-' + field );
            } else {
                cse.validity[ field ] = false;

                if (!isInitializing || val !== '') {
                    addClass( node, 'invalid-' + field );
                }
                removeClass( node, 'valid-' + field );
            }

            // Backwards compatibility
            cse.validity.luhn = cse.validity.number;

            if ( ( node.className || '' ).match( /invalid-number/ ) ) {
                addClass(node, 'invalid-luhn');
                removeClass(node, 'valid-luhn');
            } else if ( ( node.className || '' ).match( /valid-number/ ) ) {
                removeClass(node, 'invalid-luhn');
                addClass(node, 'valid-luhn');
            }

            // Continue with regular code

            if ( allowEmpty && val === '' ) {
                removeClass( node, 'valid-' + field );
                removeClass( node, 'invalid-' + field );
            }

            if ( ( node.className || '' ).match( /invalid-/ ) ) {
                addClass( node, 'invalid' );
            } else {
                removeClass( node, 'invalid' );
            }

            if ( cse.options.disabledValidClass !== true) {
                if ( ( node.className || '' ).match( /invalid-/ ) ) {
                    removeClass( node, 'valid' );
                } else {
                    addClass( node, 'valid' );
                }
            }

            cse.toggleSubmit();
        };
    };

    var DEFAULT_FIELDNAME_ATTRIBUTE = "data-encrypted-name";

    /*
     * @constructor EncryptedForm
     * 
     * @param element {DOMNode} The form element to encrypt as a DOMNode (
     * <form> ); @param key {String} The public key used to communicate with
     * Adyen @param [options] {Object} Options to pass to the constructor (
     * onsubmit {Function} and name {String} )
     * 
     * @return form {EncryptedForm} The instance of EncryptedForm.
     * 
     */

    var EncryptedForm = function ( element, key, options ) {

        if ( typeof element !== 'object' || typeof element.ownerDocument !== 'object' ) {

            throw new Error( 'Expected target element to be a HTML Form element' );
        }

        if ( 'form' !== ( element.nodeName || element.tagName || '' ).toLowerCase() ) {
            throw new Error( 'Expected target element to be a HTML Form element' );
        }

        // element and public key
        this.element = element;
        this.key = key;
        this.validity = {};

        // event logging
        evLog("initializeFormCount");

        // create an empty object if options don't exist
        this.options = options = options || {};

        if ( typeof options !== 'object' ) {
            throw new Error( 'Expected options to be an object' );
        }

        // Defaults
        if ( typeof options.numberIgnoreNonNumeric === "undefined" ) {
            options.numberIgnoreNonNumeric = true;
        }

        // Validate the custom data field name
        if ( typeof options.fieldNameAttribute !== 'string' || !options.fieldNameAttribute.match( /^data(-\w+)+$/i ) ) {
            options.fieldNameAttribute = DEFAULT_FIELDNAME_ATTRIBUTE;
        }

        this.name = options.name || 'adyen-encrypted-data';
        this.fieldNameAttribute = options.fieldNameAttribute || DEFAULT_FIELDNAME_ATTRIBUTE;
        this.onsubmit = options.onsubmit || function () {
            };

        // Boot encrypion object
        this.encryption = new Encryption( key, options );

        // Binding
        if ( this.element.addEventListener ) {
            this.element.addEventListener( 'submit', this.handleSubmit.bind( this ), false );
        } else if ( this.element.attachEvent ) {
            this.element.attachEvent( 'onsubmit', this.handleSubmit.bind( this ) );
        }

        if ( options.enableValidations !== false ) {
            this.addValidations();
        }

        for (var i = 0, c = element.elements.length; i < c; i++) {
            if (!element.elements[i]) {
                continue;
            }
            var attr = getAttribute(element.elements[i], this.options.fieldNameAttribute);

            if (typeof attr !== 'undefined' && attr !== null && attr !== '' ) {
                evLog('bind', element.elements[i], attr);
            }
        }


    };

    EncryptedForm.prototype = {

        constructor : EncryptedForm,

        /*
         * 
         * Compatibility wrapper for lte IE8. We create the wrapper once, rather
         * than doing the test on each childNode.
         * 
         * @param node {DOMNode} @param attrName {String}
         * 
         */
        hasAttribute : (document && document.documentElement && document.documentElement.hasAttribute) ? function ( node, attrName ) {
            // Native support
            return node.hasAttribute( attrName );
        } : function ( node, attrName ) {
            // IE7, IE8
            return node.attributes && node.attributes[ attrName ];
        },

        /*
         * 
         * Handles a submit of the form. It creates a hidden input with the form
         * data as serialized, encrypted JSON.
         * 
         * @param e {Event} The submit event to handle.
         * 
         */

        handleSubmit : function ( e ) {

            if ( this.options.enableValidations !== false ) {
                if ( !this.isValid() ) {
                    if ( e.preventDefault ) {
                        e.preventDefault();
                    }
                    // IE7 and lower
                    if ( window.event ) {
                        window.event.returnValue = false;
                    }
                    if ( e.originalEvent ) {
                        e.originalEvent.returnValue = false;
                    }
                    e.returnValue = false;

                    return false;
                }
            }

            this.createEncryptedField( this.encrypt() );

            this.onsubmit( e );
        },

        /*
         * Gets all encrypted fields from a root node ( usually the form element ).
         * 
         * @param node {DOMNode} The root of the form to get encrypted fields
         * from ( i.e. querySelectorAll( '[data-encrypeted-name]' ) ). @param
         * [fields] {Array} An array of fields ( used when recursively looking
         * up children ).
         * 
         * @returns fields {Array} An array of fields with a
         * data-encrypeted-name attribute. ( Alternatively returns a DOMNodeList ).
         * 
         */

        getEncryptedFields : function ( node, fields ) {

            if ( node.querySelectorAll ) {
                return node.querySelectorAll( '[' + this.fieldNameAttribute + ']' );
            }

            fields = fields || [];

            var children = node.children;
            var child;

            for ( var i = 0; i < children.length; i++ ) {
                child = children[ i ];

                if ( this.hasAttribute( child, this.fieldNameAttribute ) ) {
                    fields.push( child );
                } else {
                    this.getEncryptedFields( child, fields );
                }

            }

            return fields;

        },

        /*
         * Creates JSON object
         * 
         * @param fields {Array} An array of fields to convert to JSON.
         * 
         * @return data {JSON} The data as JavaScript Object Notation
         * 
         */
        toJSON : function ( fields ) {

            var field, data = {}, key, value;

            for ( var i = fields.length - 1; i >= 0; i-- ) {

                field = fields[ i ];

                field.removeAttribute( 'name' );
                key = field.getAttribute( this.fieldNameAttribute );
                value = field.value;

                // Cater for select boxes
                if ( field.options && typeof field.selectedIndex !== "undefined" ) {
                    value = field.options[ field.selectedIndex ].value;
                }

                data[ key ] = value;

            }

            return data;

        },

        /*
         * Encrypts data
         * 
         * @return data {String} The data in the form as encrypted and
         * serialized JSON.
         * 
         */

        encrypt : function () {

            return this.encryption.encrypt( this.toJSON( this.getEncryptedFields( this.element ) ) );

        },

        /*
         * 
         * Creates an encrypted field.
         * 
         * @param data {String} The data in the form as encrypted and serialized
         * JSON.
         * 
         */

        createEncryptedField : function ( data ) {

            var element = document.getElementById( this.name );

            if ( !element ) {
                element = document.createElement( 'input' );
                element.type = 'hidden';
                element.name = element.id = this.name;
                this.element.appendChild( element );
            }

            element.setAttribute( 'value', data );

        },

        addValidations : function () {

            var cse = this, elements = this.element.elements, c = elements.length, element, handlers = {};

            for ( ; c-- > 0; ) {
                element = elements[ c ];
                if ( !element || !element.getAttribute ) {
                    continue;
                } else if ( element.getAttribute( this.fieldNameAttribute ) === 'number' ) {
                    handlers.luhnHandler = handlers.luhnHandler || validations.createChangeHandler( cse, 'number', true );
                    addEvent( element, 'change', handlers.luhnHandler, false );
                    addEvent( element, 'keyup',  handlers.luhnHandler, false );
                    addEvent( element, 'blur',   handlers.luhnHandler, false );
                    handlers.luhnHandler( {
                        target : element,
                        isInitializing : true
                    } );
                } else if ( element.getAttribute( this.fieldNameAttribute ) === 'cvc' ) {
                    handlers.cvcHandler = handlers.cvcHandler || validations.createChangeHandler( cse, 'cvc', true );
                    addEvent( element, 'change', handlers.cvcHandler, false );
                    addEvent( element, 'keyup',  handlers.cvcHandler, false );
                    addEvent( element, 'blur',   handlers.cvcHandler, false );
                    handlers.cvcHandler( {
                        target : element,
                        isInitializing : true
                    } );
                } else if ( element.getAttribute( this.fieldNameAttribute ) === 'expiryYear' ) {
                    handlers.expiryYearHandler = handlers.expiryYearHandler || validations.createChangeHandler( cse, 'year', true );
                    addEvent( element, 'change', handlers.expiryYearHandler, false );
                    addEvent( element, 'keyup',  handlers.expiryYearHandler, false );
                    addEvent( element, 'blur',   handlers.expiryYearHandler, false );
                    handlers.expiryYearHandler( {
                        target : element,
                        isInitializing : true
                    } );
                } else if ( element.getAttribute( this.fieldNameAttribute ) === 'expiryMonth' ) {
                    handlers.expiryMonthHandler = handlers.expiryMonthHandler || validations.createChangeHandler( cse, 'month', true );
                    addEvent( element, 'change', handlers.expiryMonthHandler, false );
                    addEvent( element, 'keyup',  handlers.expiryMonthHandler, false );
                    addEvent( element, 'blur',   handlers.expiryMonthHandler, false );
                    handlers.expiryMonthHandler( {
                        target : element,
                        isInitializing : true
                    } );
                } else if ( element.getAttribute( this.fieldNameAttribute ) === 'holderName' ) {
                    handlers.holderNameHandler = handlers.holderNameHandler || validations.createChangeHandler( cse, 'holderName', false );
                    addEvent( element, 'change', handlers.holderNameHandler, false );
                    addEvent( element, 'keyup',  handlers.holderNameHandler, false );
                    addEvent( element, 'blur',   handlers.holderNameHandler, false );
                    handlers.holderNameHandler( {
                        target : element,
                        isInitializing : true
                    } );
                }
            }
        },

        addCardTypeDetection : function ( cardTypeElement ) {

            if ( typeof adyen.CardTypeDetection === "undefined" || typeof adyen.CardTypeDetection.getHandler !== "function" ) {
                return window.console && window.console.warn( "[CSE] Card type detection not available" );
            }

            var updateCardTypeDetection = adyen.CardTypeDetection.getHandler( cardTypeElement );

            var cse = this, elements = this.element.elements, c = elements.length, element, handlers = {};

            for ( ; c-- > 0; ) {
                element = elements[ c ];
                if ( !element || !element.getAttribute ) {
                    continue;
                } else if ( element.getAttribute( this.fieldNameAttribute ) === 'number' ) {
                    addEvent( element, 'change', updateCardTypeDetection, false );
                    addEvent( element, 'input', updateCardTypeDetection, false );
                    addEvent( element, 'keyup', updateCardTypeDetection, false );
                    updateCardTypeDetection( {
                        target : element
                    } );
                }
            }

        },

        isValid : function () {
            var valid = true, elements = this.element.elements, enabled;

            for ( var i in this.validity ) {
                if ( this.validity.hasOwnProperty( i ) ) {
                    valid = valid && this.validity[ i ];
                }
            }

            return valid;
        },

        toggleSubmit : function () {

            var valid = this.isValid(), elements = this.element.elements, enabled;

            enabled = valid === true || ( this.options && this.options.submitButtonAlwaysEnabled === true );

            for ( var c = elements.length; c-- > 0; ) {
                if ( elements[ c ] && ( elements[ c ].type || '' ).toLowerCase() === 'submit' ) {
                    elements[ c ].disabled = !enabled;
                }
            }

            return valid;

        },

        getVersion : function () {
            return encrypt.version;
        }

    };


    /*
     * 
     * @constructor AESKey
     * 
     * @return aes {AESKey} An AESKey with encryption methods.
     * 
     */

    var AESKey = function () {
        // empty constructor
    };

    AESKey.prototype = {

        constructor : AESKey,

        key : sjcl.random.randomWords( 8, 0 ),

        encrypt : function ( text ) {

            return this.encryptWithIv( text, sjcl.random.randomWords( 3, 0 ) );

        },

        encryptWithIv : function ( text, iv ) {

            var aes, bits, cipher, cipherIV;

            aes = new sjcl.cipher.aes( this.key );
            bits = sjcl.codec.utf8String.toBits( text );
            cipher = sjcl.mode.ccm.encrypt( aes, bits, iv );
            cipherIV = sjcl.bitArray.concat( iv, cipher );

            return sjcl.codec.base64.fromBits( cipherIV );

        }

    };

} )(this, typeof define === "function" ? define : null);