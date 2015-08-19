/*
 *
 * Client Encryption of Forms.
 *
 * Includes:
 * * RSA and ECC in JavaScript | http://www-cs-students.stanford.edu/~tjw/jsbn/
 * * Stanford Javascript Crypto Library | http://crypto.stanford.edu/sjcl/
 * * JSON in JavaScript | http://www.JSON.org/
 *
 * Version: 0_1_1
 * Author:  ADYEN (c) 2013

<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Example Payment Form</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  </head>
  <body>
		<form method="POST" action="#handler" id="adyen-encrypted-form">
			<fieldset>
				<legend>Card Details</legend>
				<div class="field">
					<label for="adyen-encrypted-form-number">Card Number
						<input type="text" id="adyen-encrypted-form-number" value="5555444433331111" size="20" autocomplete="off" data-encrypted-name="number" />
					</label>
				</div>

				<div class="field">
					<label for="adyen-encrypted-form-holder-name">Card Holder Name
						<input type="text" id="adyen-encrypted-form-holder-name" value="John Doe" size="20" autocomplete="off" data-encrypted-name="holderName" />
					</label>
				</div>

				<div class="field">
					<label for="adyen-encrypted-form-cvc">CVC
						<input type="text" id="adyen-encrypted-form-cvc" value="737" size="4" autocomplete="off" data-encrypted-name="cvc" />
					</label>
				</div>

				<div class="field">
					<label for="adyen-encrypted-form-expiry-month">Expiration Month (MM)
						<input type="text" value="06"   id="adyen-encrypted-form-expiry-month" size="2"  autocomplete="off" data-encrypted-name="expiryMonth" /> /
					</label>
					<label for="adyen-encrypted-form-expiry-year">Expiration Year (YYYY)
						<input type="text" value="2016" id="adyen-encrypted-form-expiry-year"  size="4"  autocomplete="off" data-encrypted-name="expiryYear" />
					</label>
				</div>

				<div class="field">
					<input type="hidden" id="adyen-encrypted-form-expiry-generationtime" value="generate-this-server-side" data-encrypted-name="generationtime" />
					<input type="submit" value="Submit" />
				</div>
			</fieldset>
		</form>

        <!-- How to use the Adyen encryption client-side JS library -->
        <script src="js/adyen.encrypt.min.js"></script>
        <script>
	    // generate time client side for testing... Don't deploy on a real integration!!!
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

            // the form will be encrypted before it is submitted
            adyen.encrypt.createEncryptedForm( form, key, options);
        </script>
    </body>
</html>

 *
 */
(function() {

	/* json2.js */
	if(typeof JSON!=="object"){JSON={}}(function(){function f(n){return n<10?"0"+n:n}if(typeof Date.prototype.toJSON!=="function"){Date.prototype.toJSON=function(key){return isFinite(this.valueOf())?this.getUTCFullYear()+"-"+f(this.getUTCMonth()+1)+"-"+f(this.getUTCDate())+"T"+f(this.getUTCHours())+":"+f(this.getUTCMinutes())+":"+f(this.getUTCSeconds())+"Z":null};String.prototype.toJSON=Number.prototype.toJSON=Boolean.prototype.toJSON=function(key){return this.valueOf()}}var cx=/[\u0000\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,escapable=/[\\\"\x00-\x1f\x7f-\x9f\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,gap,indent,meta={"\b":"\\b","\t":"\\t","\n":"\\n","\f":"\\f","\r":"\\r",'"':'\\"',"\\":"\\\\"},rep;function quote(string){escapable.lastIndex=0;return escapable.test(string)?'"'+string.replace(escapable,function(a){var c=meta[a];return typeof c==="string"?c:"\\u"+("0000"+a.charCodeAt(0).toString(16)).slice(-4)})+'"':'"'+string+'"'}function str(key,holder){var i,k,v,length,mind=gap,partial,value=holder[key];if(value&&typeof value==="object"&&typeof value.toJSON==="function"){value=value.toJSON(key)}if(typeof rep==="function"){value=rep.call(holder,key,value)}switch(typeof value){case"string":return quote(value);case"number":return isFinite(value)?String(value):"null";case"boolean":case"null":return String(value);case"object":if(!value){return"null"}gap+=indent;partial=[];if(Object.prototype.toString.apply(value)==="[object Array]"){length=value.length;for(i=0;i<length;i+=1){partial[i]=str(i,value)||"null"}v=partial.length===0?"[]":gap?"[\n"+gap+partial.join(",\n"+gap)+"\n"+mind+"]":"["+partial.join(",")+"]";gap=mind;return v}if(rep&&typeof rep==="object"){length=rep.length;for(i=0;i<length;i+=1){if(typeof rep[i]==="string"){k=rep[i];v=str(k,value);if(v){partial.push(quote(k)+(gap?": ":":")+v)}}}}else{for(k in value){if(Object.prototype.hasOwnProperty.call(value,k)){v=str(k,value);if(v){partial.push(quote(k)+(gap?": ":":")+v)}}}}v=partial.length===0?"{}":gap?"{\n"+gap+partial.join(",\n"+gap)+"\n"+mind+"}":"{"+partial.join(",")+"}";gap=mind;return v}}if(typeof JSON.stringify!=="function"){JSON.stringify=function(value,replacer,space){var i;gap="";indent="";if(typeof space==="number"){for(i=0;i<space;i+=1){indent+=" "}}else{if(typeof space==="string"){indent=space}}rep=replacer;if(replacer&&typeof replacer!=="function"&&(typeof replacer!=="object"||typeof replacer.length!=="number")){throw new Error("JSON.stringify")}return str("",{"":value})}}if(typeof JSON.parse!=="function"){JSON.parse=function(text,reviver){var j;function walk(holder,key){var k,v,value=holder[key];if(value&&typeof value==="object"){for(k in value){if(Object.prototype.hasOwnProperty.call(value,k)){v=walk(value,k);if(v!==undefined){value[k]=v}else{delete value[k]}}}}return reviver.call(holder,key,value)}text=String(text);cx.lastIndex=0;if(cx.test(text)){text=text.replace(cx,function(a){return"\\u"+("0000"+a.charCodeAt(0).toString(16)).slice(-4)})}if(/^[\],:{}\s]*$/.test(text.replace(/\\(?:["\\\/bfnrt]|u[0-9a-fA-F]{4})/g,"@").replace(/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g,"]").replace(/(?:^|:|,)(?:\s*\[)+/g,""))){j=eval("("+text+")");return typeof reviver==="function"?walk({"":j},""):j}throw new SyntaxError("JSON.parse")}}}());

    /* base64.js */
    function hex2b64(e){var t;var n;var r="";for(t=0;t+3<=e.length;t+=3){n=parseInt(e.substring(t,t+3),16);r+=b64map.charAt(n>>6)+b64map.charAt(n&63)}if(t+1==e.length){n=parseInt(e.substring(t,t+1),16);r+=b64map.charAt(n<<2)}else if(t+2==e.length){n=parseInt(e.substring(t,t+2),16);r+=b64map.charAt(n>>2)+b64map.charAt((n&3)<<4)}while((r.length&3)>0)r+=b64pad;return r}function b64tohex(e){var t="";var n;var r=0;var i;for(n=0;n<e.length;++n){if(e.charAt(n)==b64pad)break;v=b64map.indexOf(e.charAt(n));if(v<0)continue;if(r==0){t+=int2char(v>>2);i=v&3;r=1}else if(r==1){t+=int2char(i<<2|v>>4);i=v&15;r=2}else if(r==2){t+=int2char(i);t+=int2char(v>>2);i=v&3;r=3}else{t+=int2char(i<<2|v>>4);t+=int2char(v&15);r=0}}if(r==1)t+=int2char(i<<2);return t}function b64toBA(e){var t=b64tohex(e);var n;var r=new Array;for(n=0;2*n<t.length;++n){r[n]=parseInt(t.substring(2*n,2*n+2),16)}return r}var b64map="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";var b64pad="="

    /* jsbn.js */
    function BigInteger(e,t,n){if(e!=null)if("number"==typeof e)this.fromNumber(e,t,n);else if(t==null&&"string"!=typeof e)this.fromString(e,256);else this.fromString(e,t)}function nbi(){return new BigInteger(null)}function am1(e,t,n,r,i,s){while(--s>=0){var o=t*this[e++]+n[r]+i;i=Math.floor(o/67108864);n[r++]=o&67108863}return i}function am2(e,t,n,r,i,s){var o=t&32767,u=t>>15;while(--s>=0){var a=this[e]&32767;var f=this[e++]>>15;var l=u*a+f*o;a=o*a+((l&32767)<<15)+n[r]+(i&1073741823);i=(a>>>30)+(l>>>15)+u*f+(i>>>30);n[r++]=a&1073741823}return i}function am3(e,t,n,r,i,s){var o=t&16383,u=t>>14;while(--s>=0){var a=this[e]&16383;var f=this[e++]>>14;var l=u*a+f*o;a=o*a+((l&16383)<<14)+n[r]+i;i=(a>>28)+(l>>14)+u*f;n[r++]=a&268435455}return i}function int2char(e){return BI_RM.charAt(e)}function intAt(e,t){var n=BI_RC[e.charCodeAt(t)];return n==null?-1:n}function bnpCopyTo(e){for(var t=this.t-1;t>=0;--t)e[t]=this[t];e.t=this.t;e.s=this.s}function bnpFromInt(e){this.t=1;this.s=e<0?-1:0;if(e>0)this[0]=e;else if(e<-1)this[0]=e+DV;else this.t=0}function nbv(e){var t=nbi();t.fromInt(e);return t}function bnpFromString(e,t){var n;if(t==16)n=4;else if(t==8)n=3;else if(t==256)n=8;else if(t==2)n=1;else if(t==32)n=5;else if(t==4)n=2;else{this.fromRadix(e,t);return}this.t=0;this.s=0;var r=e.length,i=false,s=0;while(--r>=0){var o=n==8?e[r]&255:intAt(e,r);if(o<0){if(e.charAt(r)=="-")i=true;continue}i=false;if(s==0)this[this.t++]=o;else if(s+n>this.DB){this[this.t-1]|=(o&(1<<this.DB-s)-1)<<s;this[this.t++]=o>>this.DB-s}else this[this.t-1]|=o<<s;s+=n;if(s>=this.DB)s-=this.DB}if(n==8&&(e[0]&128)!=0){this.s=-1;if(s>0)this[this.t-1]|=(1<<this.DB-s)-1<<s}this.clamp();if(i)BigInteger.ZERO.subTo(this,this)}function bnpClamp(){var e=this.s&this.DM;while(this.t>0&&this[this.t-1]==e)--this.t}function bnToString(e){if(this.s<0)return"-"+this.negate().toString(e);var t;if(e==16)t=4;else if(e==8)t=3;else if(e==2)t=1;else if(e==32)t=5;else if(e==4)t=2;else return this.toRadix(e);var n=(1<<t)-1,r,i=false,s="",o=this.t;var u=this.DB-o*this.DB%t;if(o-->0){if(u<this.DB&&(r=this[o]>>u)>0){i=true;s=int2char(r)}while(o>=0){if(u<t){r=(this[o]&(1<<u)-1)<<t-u;r|=this[--o]>>(u+=this.DB-t)}else{r=this[o]>>(u-=t)&n;if(u<=0){u+=this.DB;--o}}if(r>0)i=true;if(i)s+=int2char(r)}}return i?s:"0"}function bnNegate(){var e=nbi();BigInteger.ZERO.subTo(this,e);return e}function bnAbs(){return this.s<0?this.negate():this}function bnCompareTo(e){var t=this.s-e.s;if(t!=0)return t;var n=this.t;t=n-e.t;if(t!=0)return this.s<0?-t:t;while(--n>=0)if((t=this[n]-e[n])!=0)return t;return 0}function nbits(e){var t=1,n;if((n=e>>>16)!=0){e=n;t+=16}if((n=e>>8)!=0){e=n;t+=8}if((n=e>>4)!=0){e=n;t+=4}if((n=e>>2)!=0){e=n;t+=2}if((n=e>>1)!=0){e=n;t+=1}return t}function bnBitLength(){if(this.t<=0)return 0;return this.DB*(this.t-1)+nbits(this[this.t-1]^this.s&this.DM)}function bnpDLShiftTo(e,t){var n;for(n=this.t-1;n>=0;--n)t[n+e]=this[n];for(n=e-1;n>=0;--n)t[n]=0;t.t=this.t+e;t.s=this.s}function bnpDRShiftTo(e,t){for(var n=e;n<this.t;++n)t[n-e]=this[n];t.t=Math.max(this.t-e,0);t.s=this.s}function bnpLShiftTo(e,t){var n=e%this.DB;var r=this.DB-n;var i=(1<<r)-1;var s=Math.floor(e/this.DB),o=this.s<<n&this.DM,u;for(u=this.t-1;u>=0;--u){t[u+s+1]=this[u]>>r|o;o=(this[u]&i)<<n}for(u=s-1;u>=0;--u)t[u]=0;t[s]=o;t.t=this.t+s+1;t.s=this.s;t.clamp()}function bnpRShiftTo(e,t){t.s=this.s;var n=Math.floor(e/this.DB);if(n>=this.t){t.t=0;return}var r=e%this.DB;var i=this.DB-r;var s=(1<<r)-1;t[0]=this[n]>>r;for(var o=n+1;o<this.t;++o){t[o-n-1]|=(this[o]&s)<<i;t[o-n]=this[o]>>r}if(r>0)t[this.t-n-1]|=(this.s&s)<<i;t.t=this.t-n;t.clamp()}function bnpSubTo(e,t){var n=0,r=0,i=Math.min(e.t,this.t);while(n<i){r+=this[n]-e[n];t[n++]=r&this.DM;r>>=this.DB}if(e.t<this.t){r-=e.s;while(n<this.t){r+=this[n];t[n++]=r&this.DM;r>>=this.DB}r+=this.s}else{r+=this.s;while(n<e.t){r-=e[n];t[n++]=r&this.DM;r>>=this.DB}r-=e.s}t.s=r<0?-1:0;if(r<-1)t[n++]=this.DV+r;else if(r>0)t[n++]=r;t.t=n;t.clamp()}function bnpMultiplyTo(e,t){var n=this.abs(),r=e.abs();var i=n.t;t.t=i+r.t;while(--i>=0)t[i]=0;for(i=0;i<r.t;++i)t[i+n.t]=n.am(0,r[i],t,i,0,n.t);t.s=0;t.clamp();if(this.s!=e.s)BigInteger.ZERO.subTo(t,t)}function bnpSquareTo(e){var t=this.abs();var n=e.t=2*t.t;while(--n>=0)e[n]=0;for(n=0;n<t.t-1;++n){var r=t.am(n,t[n],e,2*n,0,1);if((e[n+t.t]+=t.am(n+1,2*t[n],e,2*n+1,r,t.t-n-1))>=t.DV){e[n+t.t]-=t.DV;e[n+t.t+1]=1}}if(e.t>0)e[e.t-1]+=t.am(n,t[n],e,2*n,0,1);e.s=0;e.clamp()}function bnpDivRemTo(e,t,n){var r=e.abs();if(r.t<=0)return;var i=this.abs();if(i.t<r.t){if(t!=null)t.fromInt(0);if(n!=null)this.copyTo(n);return}if(n==null)n=nbi();var s=nbi(),o=this.s,u=e.s;var a=this.DB-nbits(r[r.t-1]);if(a>0){r.lShiftTo(a,s);i.lShiftTo(a,n)}else{r.copyTo(s);i.copyTo(n)}var f=s.t;var l=s[f-1];if(l==0)return;var c=l*(1<<this.F1)+(f>1?s[f-2]>>this.F2:0);var h=this.FV/c,p=(1<<this.F1)/c,d=1<<this.F2;var v=n.t,m=v-f,g=t==null?nbi():t;s.dlShiftTo(m,g);if(n.compareTo(g)>=0){n[n.t++]=1;n.subTo(g,n)}BigInteger.ONE.dlShiftTo(f,g);g.subTo(s,s);while(s.t<f)s[s.t++]=0;while(--m>=0){var y=n[--v]==l?this.DM:Math.floor(n[v]*h+(n[v-1]+d)*p);if((n[v]+=s.am(0,y,n,m,0,f))<y){s.dlShiftTo(m,g);n.subTo(g,n);while(n[v]<--y)n.subTo(g,n)}}if(t!=null){n.drShiftTo(f,t);if(o!=u)BigInteger.ZERO.subTo(t,t)}n.t=f;n.clamp();if(a>0)n.rShiftTo(a,n);if(o<0)BigInteger.ZERO.subTo(n,n)}function bnMod(e){var t=nbi();this.abs().divRemTo(e,null,t);if(this.s<0&&t.compareTo(BigInteger.ZERO)>0)e.subTo(t,t);return t}function Classic(e){this.m=e}function cConvert(e){if(e.s<0||e.compareTo(this.m)>=0)return e.mod(this.m);else return e}function cRevert(e){return e}function cReduce(e){e.divRemTo(this.m,null,e)}function cMulTo(e,t,n){e.multiplyTo(t,n);this.reduce(n)}function cSqrTo(e,t){e.squareTo(t);this.reduce(t)}function bnpInvDigit(){if(this.t<1)return 0;var e=this[0];if((e&1)==0)return 0;var t=e&3;t=t*(2-(e&15)*t)&15;t=t*(2-(e&255)*t)&255;t=t*(2-((e&65535)*t&65535))&65535;t=t*(2-e*t%this.DV)%this.DV;return t>0?this.DV-t:-t}function Montgomery(e){this.m=e;this.mp=e.invDigit();this.mpl=this.mp&32767;this.mph=this.mp>>15;this.um=(1<<e.DB-15)-1;this.mt2=2*e.t}function montConvert(e){var t=nbi();e.abs().dlShiftTo(this.m.t,t);t.divRemTo(this.m,null,t);if(e.s<0&&t.compareTo(BigInteger.ZERO)>0)this.m.subTo(t,t);return t}function montRevert(e){var t=nbi();e.copyTo(t);this.reduce(t);return t}function montReduce(e){while(e.t<=this.mt2)e[e.t++]=0;for(var t=0;t<this.m.t;++t){var n=e[t]&32767;var r=n*this.mpl+((n*this.mph+(e[t]>>15)*this.mpl&this.um)<<15)&e.DM;n=t+this.m.t;e[n]+=this.m.am(0,r,e,t,0,this.m.t);while(e[n]>=e.DV){e[n]-=e.DV;e[++n]++}}e.clamp();e.drShiftTo(this.m.t,e);if(e.compareTo(this.m)>=0)e.subTo(this.m,e)}function montSqrTo(e,t){e.squareTo(t);this.reduce(t)}function montMulTo(e,t,n){e.multiplyTo(t,n);this.reduce(n)}function bnpIsEven(){return(this.t>0?this[0]&1:this.s)==0}function bnpExp(e,t){if(e>4294967295||e<1)return BigInteger.ONE;var n=nbi(),r=nbi(),i=t.convert(this),s=nbits(e)-1;i.copyTo(n);while(--s>=0){t.sqrTo(n,r);if((e&1<<s)>0)t.mulTo(r,i,n);else{var o=n;n=r;r=o}}return t.revert(n)}function bnModPowInt(e,t){var n;if(e<256||t.isEven())n=new Classic(t);else n=new Montgomery(t);return this.exp(e,n)}var dbits;var canary=0xdeadbeefcafe;var j_lm=(canary&16777215)==15715070;if(j_lm&&navigator.appName=="Microsoft Internet Explorer"){BigInteger.prototype.am=am2;dbits=30}else if(j_lm&&navigator.appName!="Netscape"){BigInteger.prototype.am=am1;dbits=26}else{BigInteger.prototype.am=am3;dbits=28}BigInteger.prototype.DB=dbits;BigInteger.prototype.DM=(1<<dbits)-1;BigInteger.prototype.DV=1<<dbits;var BI_FP=52;BigInteger.prototype.FV=Math.pow(2,BI_FP);BigInteger.prototype.F1=BI_FP-dbits;BigInteger.prototype.F2=2*dbits-BI_FP;var BI_RM="0123456789abcdefghijklmnopqrstuvwxyz";var BI_RC=new Array;var rr,vv;rr="0".charCodeAt(0);for(vv=0;vv<=9;++vv)BI_RC[rr++]=vv;rr="a".charCodeAt(0);for(vv=10;vv<36;++vv)BI_RC[rr++]=vv;rr="A".charCodeAt(0);for(vv=10;vv<36;++vv)BI_RC[rr++]=vv;Classic.prototype.convert=cConvert;Classic.prototype.revert=cRevert;Classic.prototype.reduce=cReduce;Classic.prototype.mulTo=cMulTo;Classic.prototype.sqrTo=cSqrTo;Montgomery.prototype.convert=montConvert;Montgomery.prototype.revert=montRevert;Montgomery.prototype.reduce=montReduce;Montgomery.prototype.mulTo=montMulTo;Montgomery.prototype.sqrTo=montSqrTo;BigInteger.prototype.copyTo=bnpCopyTo;BigInteger.prototype.fromInt=bnpFromInt;BigInteger.prototype.fromString=bnpFromString;BigInteger.prototype.clamp=bnpClamp;BigInteger.prototype.dlShiftTo=bnpDLShiftTo;BigInteger.prototype.drShiftTo=bnpDRShiftTo;BigInteger.prototype.lShiftTo=bnpLShiftTo;BigInteger.prototype.rShiftTo=bnpRShiftTo;BigInteger.prototype.subTo=bnpSubTo;BigInteger.prototype.multiplyTo=bnpMultiplyTo;BigInteger.prototype.squareTo=bnpSquareTo;BigInteger.prototype.divRemTo=bnpDivRemTo;BigInteger.prototype.invDigit=bnpInvDigit;BigInteger.prototype.isEven=bnpIsEven;BigInteger.prototype.exp=bnpExp;BigInteger.prototype.toString=bnToString;BigInteger.prototype.negate=bnNegate;BigInteger.prototype.abs=bnAbs;BigInteger.prototype.compareTo=bnCompareTo;BigInteger.prototype.bitLength=bnBitLength;BigInteger.prototype.mod=bnMod;BigInteger.prototype.modPowInt=bnModPowInt;BigInteger.ZERO=nbv(0);BigInteger.ONE=nbv(1)

    /* prng4.js */
    function Arcfour(){this.i=0;this.j=0;this.S=new Array}function ARC4init(e){var t,n,r;for(t=0;t<256;++t)this.S[t]=t;n=0;for(t=0;t<256;++t){n=n+this.S[t]+e[t%e.length]&255;r=this.S[t];this.S[t]=this.S[n];this.S[n]=r}this.i=0;this.j=0}function ARC4next(){var e;this.i=this.i+1&255;this.j=this.j+this.S[this.i]&255;e=this.S[this.i];this.S[this.i]=this.S[this.j];this.S[this.j]=e;return this.S[e+this.S[this.i]&255]}function prng_newstate(){return new Arcfour}Arcfour.prototype.init=ARC4init;Arcfour.prototype.next=ARC4next;var rng_psize=256

    /* rng.js */
    function rng_seed_int(e){rng_pool[rng_pptr++]^=e&255;rng_pool[rng_pptr++]^=e>>8&255;rng_pool[rng_pptr++]^=e>>16&255;rng_pool[rng_pptr++]^=e>>24&255;if(rng_pptr>=rng_psize)rng_pptr-=rng_psize}function rng_seed_time(){rng_seed_int((new Date).getTime())}function rng_get_byte(){if(rng_state==null){rng_seed_time();rng_state=prng_newstate();rng_state.init(rng_pool);for(rng_pptr=0;rng_pptr<rng_pool.length;++rng_pptr)rng_pool[rng_pptr]=0;rng_pptr=0}return rng_state.next()}function rng_get_bytes(e){var t;for(t=0;t<e.length;++t)e[t]=rng_get_byte()}function SecureRandom(){}var rng_state;var rng_pool;var rng_pptr;if(rng_pool==null){rng_pool=new Array;rng_pptr=0;var t;if(navigator.appName=="Netscape"&&navigator.appVersion<"5"&&window.crypto){var z=window.crypto.random(32);for(t=0;t<z.length;++t)rng_pool[rng_pptr++]=z.charCodeAt(t)&255}while(rng_pptr<rng_psize){t=Math.floor(65536*Math.random());rng_pool[rng_pptr++]=t>>>8;rng_pool[rng_pptr++]=t&255}rng_pptr=0;rng_seed_time()}SecureRandom.prototype.nextBytes=rng_get_bytes

    /* rsa.js */
    function parseBigInt(str,r){return new BigInteger(str,r)}function pkcs1pad2(b,n){if(n<b.length+11){alert("Message too long for RSA");return null}var ba=new Array();var i=b.length-1;while(i>=0&&n>0){ba[--n]=b[i--]}ba[--n]=0;var rng=new SecureRandom();var x=new Array();while(n>2){x[0]=0;while(x[0]==0){rng.nextBytes(x)}ba[--n]=x[0]}ba[--n]=2;ba[--n]=0;return new BigInteger(ba)}function RSAKey(){this.n=null;this.e=0;this.d=null;this.p=null;this.q=null;this.dmp1=null;this.dmq1=null;this.coeff=null}function RSASetPublic(N,E){if(N!=null&&E!=null&&N.length>0&&E.length>0){this.n=parseBigInt(N,16);this.e=parseInt(E,16)}else{alert("Invalid RSA public key")}}function RSADoPublic(x){return x.modPowInt(this.e,this.n)}function RSAEncrypt(bytes){var m=pkcs1pad2(bytes,(this.n.bitLength()+7)>>3);if(m==null){return null}var c=this.doPublic(m);if(c==null){return null}var h=c.toString(16);if((h.length&1)==0){return h}else{return"0"+h}}function RSAEncryptB64(bytes){var h=this.encrypt(bytes);if(h){return hex2b64(h)}else{return null}}RSAKey.prototype.doPublic=RSADoPublic;RSAKey.prototype.setPublic=RSASetPublic;RSAKey.prototype.encrypt=RSAEncrypt;RSAKey.prototype.encrypt_b64=RSAEncryptB64;

    /* sjcl.js */

	"use strict";var sjcl={cipher:{},hash:{},keyexchange:{},mode:{},misc:{},codec:{},exception:{corrupt:function(a){this.toString=function(){return"CORRUPT: "+this.message};this.message=a},invalid:function(a){this.toString=function(){return"INVALID: "+this.message};this.message=a},bug:function(a){this.toString=function(){return"BUG: "+this.message};this.message=a},notReady:function(a){this.toString=function(){return"NOT READY: "+this.message};this.message=a}}};if(typeof module!="undefined"&&module.exports){module.exports=sjcl}sjcl.cipher.aes=function(a){this.g[0][0][0]||this.u();var b,c,d,e,f=this.g[0][4],g=this.g[1];b=a.length;var h=1;if(b!==4&&b!==6&&b!==8){throw new sjcl.exception.invalid("invalid aes key size")}this.a=[d=a.slice(0),e=[]];for(a=b;a<4*b+28;a++){c=d[a-1];if(a%b===0||b===8&&a%b===4){c=f[c>>>24]<<24^f[c>>16&255]<<16^f[c>>8&255]<<8^f[c&255];if(a%b===0){c=c<<8^c>>>24^h<<24;h=h<<1^(h>>7)*283}}d[a]=d[a-b]^c}for(b=0;a;b++,a--){c=d[b&3?a:a-4];e[b]=a<=4||b<4?c:g[0][f[c>>>24]]^g[1][f[c>>16&255]]^g[2][f[c>>8&255]]^g[3][f[c&255]]}};sjcl.cipher.aes.prototype={encrypt:function(a){return this.D(a,0)},decrypt:function(a){return this.D(a,1)},g:[[[],[],[],[],[]],[[],[],[],[],[]]],u:function(){var a=this.g[0],b=this.g[1],c=a[4],d=b[4],e,f,g,h=[],i=[],k,j,l,m;for(e=0;e<256;e++){i[(h[e]=e<<1^(e>>7)*283)^e]=e}for(f=g=0;!c[f];f^=k||1,g=i[g]||1){l=g^g<<1^g<<2^g<<3^g<<4;l=l>>8^l&255^99;c[f]=l;d[l]=f;j=h[e=h[k=h[f]]];m=j*16843009^e*65537^k*257^f*16843008;j=h[l]*257^l*16843008;for(e=0;e<4;e++){a[e][f]=j=j<<24^j>>>8;b[e][l]=m=m<<24^m>>>8}}for(e=0;e<5;e++){a[e]=a[e].slice(0);b[e]=b[e].slice(0)}},D:function(a,b){if(a.length!==4){throw new sjcl.exception.invalid("invalid aes block size")}var c=this.a[b],d=a[0]^c[0],e=a[b?3:1]^c[1],f=a[2]^c[2];a=a[b?1:3]^c[3];var g,h,i,k=c.length/4-2,j,l=4,m=[0,0,0,0];g=this.g[b];var n=g[0],o=g[1],p=g[2],q=g[3],r=g[4];for(j=0;j<k;j++){g=n[d>>>24]^o[e>>16&255]^p[f>>8&255]^q[a&255]^c[l];h=n[e>>>24]^o[f>>16&255]^p[a>>8&255]^q[d&255]^c[l+1];i=n[f>>>24]^o[a>>16&255]^p[d>>8&255]^q[e&255]^c[l+2];a=n[a>>>24]^o[d>>16&255]^p[e>>8&255]^q[f&255]^c[l+3];l+=4;d=g;e=h;f=i}for(j=0;j<4;j++){m[b?3&-j:j]=r[d>>>24]<<24^r[e>>16&255]<<16^r[f>>8&255]<<8^r[a&255]^c[l++];g=d;d=e;e=f;f=a;a=g}return m}};sjcl.bitArray={bitSlice:function(a,b,c){a=sjcl.bitArray.L(a.slice(b/32),32-(b&31)).slice(1);return c===undefined?a:sjcl.bitArray.clamp(a,c-b)},extract:function(a,b,c){var d=Math.floor(-b-c&31);return((b+c-1^b)&-32?a[b/32|0]<<32-d^a[b/32+1|0]>>>d:a[b/32|0]>>>d)&(1<<c)-1},concat:function(a,b){if(a.length===0||b.length===0){return a.concat(b)}var c=a[a.length-1],d=sjcl.bitArray.getPartial(c);return d===32?a.concat(b):sjcl.bitArray.L(b,d,c|0,a.slice(0,a.length-1))},bitLength:function(a){var b=a.length;if(b===0){return 0}return(b-1)*32+sjcl.bitArray.getPartial(a[b-1])},clamp:function(a,b){if(a.length*32<b){return a}a=a.slice(0,Math.ceil(b/32));var c=a.length;b&=31;if(c>0&&b){a[c-1]=sjcl.bitArray.partial(b,a[c-1]&2147483648>>b-1,1)}return a},partial:function(a,b,c){if(a===32){return b}return(c?b|0:b<<32-a)+a*1099511627776},getPartial:function(a){return Math.round(a/1099511627776)||32},equal:function(a,b){if(sjcl.bitArray.bitLength(a)!==sjcl.bitArray.bitLength(b)){return false}var c=0,d;for(d=0;d<a.length;d++){c|=a[d]^b[d]}return c===0},L:function(a,b,c,d){var e;e=0;if(d===undefined){d=[]}for(;b>=32;b-=32){d.push(c);c=0}if(b===0){return d.concat(a)}for(e=0;e<a.length;e++){d.push(c|a[e]>>>b);c=a[e]<<32-b}e=a.length?a[a.length-1]:0;a=sjcl.bitArray.getPartial(e);d.push(sjcl.bitArray.partial(b+a&31,b+a>32?c:d.pop(),1));return d},M:function(a,b){return[a[0]^b[0],a[1]^b[1],a[2]^b[2],a[3]^b[3]]}};sjcl.codec.utf8String={fromBits:function(a){var b="",c=sjcl.bitArray.bitLength(a),d,e;for(d=0;d<c/8;d++){if((d&3)===0){e=a[d/4]}b+=String.fromCharCode(e>>>24);e<<=8}return decodeURIComponent(escape(b))},toBits:function(a){a=unescape(encodeURIComponent(a));var b=[],c,d=0;for(c=0;c<a.length;c++){d=d<<8|a.charCodeAt(c);if((c&3)===3){b.push(d);d=0}}c&3&&b.push(sjcl.bitArray.partial(8*(c&3),d));return b}};sjcl.codec.base64={A:"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",fromBits:function(a,b,c){var d="",e=0,f=sjcl.codec.base64.A,g=0,h=sjcl.bitArray.bitLength(a);if(c){f=f.substr(0,62)+"-_"}for(c=0;d.length*6<h;){d+=f.charAt((g^a[c]>>>e)>>>26);if(e<6){g=a[c]<<6-e;e+=26;c++}else{g<<=6;e-=6}}for(;d.length&3&&!b;){d+="="}return d},toBits:function(a,b){a=a.replace(/\s|=/g,"");var c=[],d=0,e=sjcl.codec.base64.A,f=0,g;if(b){e=e.substr(0,62)+"-_"}for(b=0;b<a.length;b++){g=e.indexOf(a.charAt(b));if(g<0){throw new sjcl.exception.invalid("this isn't base64!")}if(d>26){d-=26;c.push(f^g>>>d);f=g<<32-d}else{d+=6;f^=g<<32-d}}d&56&&c.push(sjcl.bitArray.partial(d&56,f,1));return c}};sjcl.codec.base64url={fromBits:function(a){return sjcl.codec.base64.fromBits(a,1,1)},toBits:function(a){return sjcl.codec.base64.toBits(a,1)}};sjcl.codec.bytes={fromBits:function(a){var b=[],c=sjcl.bitArray.bitLength(a),d,e;for(d=0;d<c/8;d++){if((d&3)===0){e=a[d/4]}b.push(e>>>24);e<<=8}return b},toBits:function(a){var b=[],c,d=0;for(c=0;c<a.length;c++){d=d<<8|a[c];if((c&3)===3){b.push(d);d=0}}c&3&&b.push(sjcl.bitArray.partial(8*(c&3),d));return b}};sjcl.hash.sha256=function(a){this.a[0]||this.u();if(a){this.l=a.l.slice(0);this.h=a.h.slice(0);this.d=a.d}else{this.reset()}};sjcl.hash.sha256.hash=function(a){return(new sjcl.hash.sha256).update(a).finalize()};sjcl.hash.sha256.prototype={blockSize:512,reset:function(){this.l=this.J.slice(0);this.h=[];this.d=0;return this},update:function(a){if(typeof a==="string"){a=sjcl.codec.utf8String.toBits(a)}var b,c=this.h=sjcl.bitArray.concat(this.h,a);b=this.d;a=this.d=b+sjcl.bitArray.bitLength(a);for(b=512+b&-512;b<=a;b+=512){this.z(c.splice(0,16))}return this},finalize:function(){var a,b=this.h,c=this.l;b=sjcl.bitArray.concat(b,[sjcl.bitArray.partial(1,1)]);for(a=b.length+2;a&15;a++){b.push(0)}b.push(Math.floor(this.d/4294967296));for(b.push(this.d|0);b.length;){this.z(b.splice(0,16))}this.reset();return c},J:[],a:[],u:function(){function a(e){return(e-Math.floor(e))*4294967296|0}var b=0,c=2,d;a:for(;b<64;c++){for(d=2;d*d<=c;d++){if(c%d===0){continue a}}if(b<8){this.J[b]=a(Math.pow(c,0.5))}this.a[b]=a(Math.pow(c,1/3));b++}},z:function(a){var b,c,d=a.slice(0),e=this.l,f=this.a,g=e[0],h=e[1],i=e[2],k=e[3],j=e[4],l=e[5],m=e[6],n=e[7];for(a=0;a<64;a++){if(a<16){b=d[a]}else{b=d[a+1&15];c=d[a+14&15];b=d[a&15]=(b>>>7^b>>>18^b>>>3^b<<25^b<<14)+(c>>>17^c>>>19^c>>>10^c<<15^c<<13)+d[a&15]+d[a+9&15]|0}b=b+n+(j>>>6^j>>>11^j>>>25^j<<26^j<<21^j<<7)+(m^j&(l^m))+f[a];n=m;m=l;l=j;j=k+b|0;k=i;i=h;h=g;g=b+(h&i^k&(h^i))+(h>>>2^h>>>13^h>>>22^h<<30^h<<19^h<<10)|0}e[0]=e[0]+g|0;e[1]=e[1]+h|0;e[2]=e[2]+i|0;e[3]=e[3]+k|0;e[4]=e[4]+j|0;e[5]=e[5]+l|0;e[6]=e[6]+m|0;e[7]=e[7]+n|0}};sjcl.mode.ccm={name:"ccm",encrypt:function(a,b,c,d,e){var f,g=b.slice(0),h=sjcl.bitArray,i=h.bitLength(c)/8,k=h.bitLength(g)/8;e=e||64;d=d||[];if(i<7){throw new sjcl.exception.invalid("ccm: iv must be at least 7 bytes")}for(f=2;f<4&&k>>>8*f;f++){}if(f<15-i){f=15-i}c=h.clamp(c,8*(15-f));b=sjcl.mode.ccm.C(a,b,c,d,e,f);g=sjcl.mode.ccm.F(a,g,c,b,e,f);return h.concat(g.data,g.tag)},decrypt:function(a,b,c,d,e){e=e||64;d=d||[];var f=sjcl.bitArray,g=f.bitLength(c)/8,h=f.bitLength(b),i=f.clamp(b,h-e),k=f.bitSlice(b,h-e);h=(h-e)/8;if(g<7){throw new sjcl.exception.invalid("ccm: iv must be at least 7 bytes")}for(b=2;b<4&&h>>>8*b;b++){}if(b<15-g){b=15-g}c=f.clamp(c,8*(15-b));i=sjcl.mode.ccm.F(a,i,c,k,e,b);a=sjcl.mode.ccm.C(a,i.data,c,d,e,b);if(!f.equal(i.tag,a)){throw new sjcl.exception.corrupt("ccm: tag doesn't match")}return i.data},C:function(a,b,c,d,e,f){var g=[],h=sjcl.bitArray,i=h.M;e/=8;if(e%2||e<4||e>16){throw new sjcl.exception.invalid("ccm: invalid tag length")}if(d.length>4294967295||b.length>4294967295){throw new sjcl.exception.bug("ccm: can't deal with 4GiB or more data")}f=[h.partial(8,(d.length?64:0)|e-2<<2|f-1)];f=h.concat(f,c);f[3]|=h.bitLength(b)/8;f=a.encrypt(f);if(d.length){c=h.bitLength(d)/8;if(c<=65279){g=[h.partial(16,c)]}else{if(c<=4294967295){g=h.concat([h.partial(16,65534)],[c])}}g=h.concat(g,d);for(d=0;d<g.length;d+=4){f=a.encrypt(i(f,g.slice(d,d+4).concat([0,0,0])))}}for(d=0;d<b.length;d+=4){f=a.encrypt(i(f,b.slice(d,d+4).concat([0,0,0])))}return h.clamp(f,e*8)},F:function(a,b,c,d,e,f){var g,h=sjcl.bitArray;g=h.M;var i=b.length,k=h.bitLength(b);c=h.concat([h.partial(8,f-1)],c).concat([0,0,0]).slice(0,4);d=h.bitSlice(g(d,a.encrypt(c)),0,e);if(!i){return{tag:d,data:[]}}for(g=0;g<i;g+=4){c[3]++;e=a.encrypt(c);b[g]^=e[0];b[g+1]^=e[1];b[g+2]^=e[2];b[g+3]^=e[3]}return{tag:d,data:h.clamp(b,k)}}};sjcl.misc.hmac=function(a,b){this.I=b=b||sjcl.hash.sha256;var c=[[],[]],d=b.prototype.blockSize/32;this.j=[new b,new b];if(a.length>d){a=b.hash(a)}for(b=0;b<d;b++){c[0][b]=a[b]^909522486;c[1][b]=a[b]^1549556828}this.j[0].update(c[0]);this.j[1].update(c[1])};sjcl.misc.hmac.prototype.encrypt=sjcl.misc.hmac.prototype.mac=function(a){a=(new this.I(this.j[0])).update(a).finalize();return(new this.I(this.j[1])).update(a).finalize()};sjcl.misc.pbkdf2=function(a,b,c,d,e){c=c||1000;if(d<0||c<0){throw sjcl.exception.invalid("invalid params to pbkdf2")}if(typeof a==="string"){a=sjcl.codec.utf8String.toBits(a)}e=e||sjcl.misc.hmac;a=new e(a);var f,g,h,i,k=[],j=sjcl.bitArray;for(i=1;32*k.length<(d||1);i++){e=f=a.encrypt(j.concat(b,[i]));for(g=1;g<c;g++){f=a.encrypt(f);for(h=0;h<f.length;h++){e[h]^=f[h]}}k=k.concat(e)}if(d){k=j.clamp(k,d)}return k};sjcl.random={randomWords:function(a,b){var c=[];b=this.isReady(b);var d;if(b===0){throw new sjcl.exception.notReady("generator isn't seeded")}else{b&2&&this.Q(!(b&1))}for(b=0;b<a;b+=4){(b+1)%65536===0&&this.H();d=this.t();c.push(d[0],d[1],d[2],d[3])}this.H();return c.slice(0,a)},setDefaultParanoia:function(a){this.r=a},addEntropy:function(a,b,c){c=c||"user";var d,e,f=(new Date).valueOf(),g=this.o[c],h=this.isReady(),i=0;d=this.B[c];if(d===undefined){d=this.B[c]=this.O++}if(g===undefined){g=this.o[c]=0}this.o[c]=(this.o[c]+1)%this.b.length;switch(typeof a){case"number":if(b===undefined){b=1}this.b[g].update([d,this.s++,1,b,f,1,a|0]);break;case"object":c=Object.prototype.toString.call(a);if(c==="[object Uint32Array]"){e=[];for(c=0;c<a.length;c++){e.push(a[c])}a=e}else{if(c!=="[object Array]"){i=1}for(c=0;c<a.length&&!i;c++){if(typeof a[c]!="number"){i=1}}}if(!i){if(b===undefined){for(c=b=0;c<a.length;c++){for(e=a[c];e>0;){b++;e>>>=1}}}this.b[g].update([d,this.s++,2,b,f,a.length].concat(a))}break;case"string":if(b===undefined){b=a.length}this.b[g].update([d,this.s++,3,b,f,a.length]);this.b[g].update(a);break;default:i=1}if(i){throw new sjcl.exception.bug("random: addEntropy only supports number, array of numbers or string")}this.i[g]+=b;this.e+=b;if(h===0){this.isReady()!==0&&this.G("seeded",Math.max(this.f,this.e));this.G("progress",this.getProgress())}},isReady:function(a){a=this.w[a!==undefined?a:this.r];return this.f&&this.f>=a?this.i[0]>80&&(new Date).valueOf()>this.K?3:1:this.e>=a?2:0},getProgress:function(a){a=this.w[a?a:this.r];return this.f>=a?1:this.e>a?1:this.e/a},startCollectors:function(){if(!this.k){if(window.addEventListener){window.addEventListener("load",this.m,false);window.addEventListener("mousemove",this.n,false)}else{if(document.attachEvent){document.attachEvent("onload",this.m);document.attachEvent("onmousemove",this.n)}else{throw new sjcl.exception.bug("can't attach event")}}this.k=true}},stopCollectors:function(){if(this.k){if(window.removeEventListener){window.removeEventListener("load",this.m,false);window.removeEventListener("mousemove",this.n,false)}else{if(window.detachEvent){window.detachEvent("onload",this.m);window.detachEvent("onmousemove",this.n)}}this.k=false}},addEventListener:function(a,b){this.p[a][this.N++]=b},removeEventListener:function(a,b){var c;a=this.p[a];var d=[];for(c in a){a.hasOwnProperty(c)&&a[c]===b&&d.push(c)}for(b=0;b<d.length;b++){c=d[b];delete a[c]}},b:[new sjcl.hash.sha256],i:[0],v:0,o:{},s:0,B:{},O:0,f:0,e:0,K:0,a:[0,0,0,0,0,0,0,0],c:[0,0,0,0],q:undefined,r:6,k:false,p:{progress:{},seeded:{}},N:0,w:[0,48,64,96,128,192,256,384,512,768,1024],t:function(){for(var a=0;a<4;a++){this.c[a]=this.c[a]+1|0;if(this.c[a]){break}}return this.q.encrypt(this.c)},H:function(){this.a=this.t().concat(this.t());this.q=new sjcl.cipher.aes(this.a)},P:function(a){this.a=sjcl.hash.sha256.hash(this.a.concat(a));this.q=new sjcl.cipher.aes(this.a);for(a=0;a<4;a++){this.c[a]=this.c[a]+1|0;if(this.c[a]){break}}},Q:function(a){var b=[],c=0,d;this.K=b[0]=(new Date).valueOf()+30000;for(d=0;d<16;d++){b.push(Math.random()*4294967296|0)}for(d=0;d<this.b.length;d++){b=b.concat(this.b[d].finalize());c+=this.i[d];this.i[d]=0;if(!a&&this.v&1<<d){break}}if(this.v>=1<<this.b.length){this.b.push(new sjcl.hash.sha256);this.i.push(0)}this.e-=c;if(c>this.f){this.f=c}this.v++;this.P(b)},n:function(a){sjcl.random.addEntropy([a.x||a.clientX||a.offsetX||0,a.y||a.clientY||a.offsetY||0],2,"mouse")},m:function(){sjcl.random.addEntropy((new Date).valueOf(),2,"loadtime")},G:function(a,b){var c;a=sjcl.random.p[a];var d=[];for(c in a){a.hasOwnProperty(c)&&d.push(a[c])}for(c=0;c<d.length;c++){d[c](b)}}};try{var s=new Uint32Array(32);crypto.getRandomValues(s);sjcl.random.addEntropy(s,1024,"crypto['getRandomValues']")}catch(t){};

    /* adyen */
    var adyen = window.adyen = window.adyen || {};

    var encrypt = adyen.encrypt = adyen.encrypt || {
        createEncryptedForm: function( form, key, name ) {
            return new EncryptedForm( form, key, name );
        }
    };

    encrypt.version = '0_1_1';


    /*
     *
     *
     *
     * @constructor EncryptedForm
     *
     * @param element {DOMNode}                 The form element to encrypt as a DOMNode ( <form> );
     * @param key {String}                      The public key used to communicate with Adyen
     * @param [options] {Object}                Options to pass to the constructor ( onsubmit {Function} and name {String} )
     *
     * @return form {EncryptedForm}             The instance of EncryptedForm.
     *
     */

    var EncryptedForm = encrypt.EncryptedForm = function( element, key, options ) {

        // element and public key
        this.element = element;
        this.key = key;

        // create an empty object if options don't exist
        options = options || {};

        this.name = options.name || 'adyen-encrypted-data';
        this.force = options.force || false;
        this.id = options.id || this.name;
        this.onsubmit = options.onsubmit || function() {};
        this.dataEncryptedName = options.dataEncryptedName || "data-encrypted-name";

        if ( this.force !== false ) {
            this.createEncryptedField( this.encrypt() );
        } else {
            if ( this.element.addEventListener ) {
                this.element.addEventListener( 'submit', this.handleSubmit.bind( this ), false );
            } else if ( this.element.attachEvent ) {
                this.element.attachEvent( 'onsubmit', this.handleSubmit.bind( this ) );
            }
        }

    };

    EncryptedForm.prototype = {

        constructor: EncryptedForm,

        /*
         *
         * Handles a submit of the form.
         * It creates a hidden input with the form data as serialized, encrypted JSON.
         *
         * @param e {Event}                     The submit event to handle.
         *
         */

        handleSubmit: function( e ) {
            this.createEncryptedField( this.encrypt() );
			this.onsubmit(e);
        },

        /*
         * Creates an RSA key based on the public key.
         *
         * @returns rsa {RSAKey}                An RSAKey based on the public key provided.
         *
         */

        createRSAKey: function() {

            var k = this.key.split('|');

            if ( k.length != 2 ) {
                throw 'Malformed public key';
            }

            var exp = k[0];
            var mod = k[1];

            // would be better to put it in a package.
            var rsa = new RSAKey();
            rsa.setPublic( mod, exp );

            return rsa;

        },

        /*
         * Creates an AES key.
         *
         * @returns aes {Object}                An AESKey with encryption methods.
         *
         */

        createAESKey: function() {
            return new AESKey();
        },

        /*
         * Gets all encrypted fields from a root node ( usually the form element ).
         *
         * @param node {DOMNode}                The root of the form to get encrypted fields from ( i.e. querySelectorAll( '[data-encrypeted-name]' ) ).
         * @param [fields] {Array}              An array of fields ( used when recursively looking up children ).
         *
         * @returns fields {Array}              An array of fields with a data-encrypeted-name attribute. ( Alternatively returns a DOMNodeList ).
         *
         */

        getEncryptedFields: function( node, fields ) {

            if ( node.querySelectorAll ) {
                return node.querySelectorAll( '['+ this.dataEncryptedName + ']' );
            }

            fields = fields || [];

            var children = node.children;
            var child;

            for ( var i = 0; i < children.length; i++ ) {
                child = children[i];

                if ( child.hasAttribute(this.dataEncryptedName) ) {
                    fields.push( child )
                } else {
                    this.getEncryptedFields( child, fields );
                }

            };

            return fields;

        },

        /*
         * Creates JSON object
         *
         * @param fields {Array}                An array of fields to convert to JSON.
         *
         * @return data {JSON}                  The data as JavaScript Object Notation
         *
         */

        toJSON: function( fields ) {

            var field;

            var data = {};
            var key, value;

            for ( var i = fields.length - 1; i >= 0; i-- ) {

                field = fields[i];

                field.removeAttribute('name');
                key = field.getAttribute(this.dataEncryptedName);
                value = field.value;
                data[ key ] = value;
            };
            return data;

        },

        /*
         * Encrypts data
         *
         * @return data {String}                  The data in the form as encrypted and serialized JSON.
         *
         */

        encrypt: function() {

            var data = this.toJSON( this.getEncryptedFields( this.element ) );

            var rsa, aes, cipher, encoded, encrypted, prefix;

                rsa = this.createRSAKey();
                aes = this.createAESKey();

                cipher      = aes.encrypt( JSON.stringify( data ) );
                keybytes    = sjcl.codec.bytes.fromBits( aes.key );
                encrypted   = rsa.encrypt_b64( keybytes );
                prefix      = 'adyenjs_' + encrypt.version + '$';

            return [ prefix, encrypted, '$', cipher ].join('');

        },

        /*
         *
         * Creates an encrypted field.
         *
         * @param data {String}                    The data in the form as encrypted and serialized JSON.
         *
         */

        createEncryptedField: function( data ) {

            var element = document.getElementById( this.name );

            if ( !element ) {
                element = document.createElement( 'input' );
                element.type = 'hidden';
                element.name = this.name;
                element.id = this.id;
                this.element.appendChild( element );
            }
            element.setAttribute( 'value', data );
        }

    };


    /*
     *
     * @constructor AESKey
     *
     * @return aes {AESKey}                 An AESKey with encryption methods.
     *
     */

    var AESKey = function() {
        // empty constructor
    };

    AESKey.prototype = {

        constructor: AESKey,

        key: sjcl.random.randomWords( 8, 0 ),

        encrypt: function ( text ) {

            return this.encryptWithIv( text, sjcl.random.randomWords( 3, 0 ) );

        },

        encryptWithIv: function ( text, iv ) {

            var aes, bits, cipher, cipherIV;

                aes = new sjcl.cipher.aes( this.key );
                bits = sjcl.codec.utf8String.toBits( text );
                cipher = sjcl.mode.ccm.encrypt( aes, bits, iv );
                cipherIV = sjcl.bitArray.concat( iv, cipher );

            return sjcl.codec.base64.fromBits( cipherIV );

        }

    };

})();
