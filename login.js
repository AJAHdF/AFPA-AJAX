import { Extenders } from "./Extenders";
Extenders.init();


const validateFormData = (plainFormData) => {
    const errorMessages = {};
    for(const key in plainFormData){
        let currentValue = plainFormData[key];
        if(key == 'email'){
            const regex = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
            errorMessages[key] = !currentValue.match(regex) ? 'Saisie invalide !' : '';
        }
        if(key === 'password'){
            errorMessages[key] = currentValue.length < 6 || currentValue.trim() === '' ? 'Saisie invalide !' : '';
            //validateFormData({'pwConfirm':document.querySelector('#registrationForm input#pwConfirm').value})
        }
    }
    for(const errorsKey in errorMessages){
        const errorMsgElt = document.getElementById(`${errorsKey}ErrorMsg`);
        const errorMsg = errorMessages[errorsKey]
        errorMsgElt.innerText = errorMsg;
        allInputsValues[errorsKey] = errorMsg === '';
    }
    let isValid = Object.values(allInputsValues).every(item => {return item});
    if(isValid) {
        document.querySelector("#loginForm #submitBtn").removeAttribute("disabled");
    }
    else{
        document.querySelector("#loginForm #submitBtn").setAttribute("disabled",true);
    }
    return isValid;
}

const handleLoginFormSubmit = (evt) => {
    evt.preventDefault();
    const form = evt.currentTarget;
    const formData = new FormData(form);
    let plainFormData = Object.fromEntries(formData.entries());
    if(validateFormData(plainFormData)){
        const formDataJsonString = JSON.stringify(plainFormData);
        const options = {
            method : 'post',
            body: formDataJsonString
        }
        // fetch("/rest/login.php", options)
        fetch("/api/account/login", options)
            .then(resp => resp.text())
            .then(text => {
                console.log(text);
                const json = JSON.tryParse(text);
                console.log(json);
                //localStorage.clear();
                localStorage.setItem("auth", text);
                //const cookie = document.cookie;
                const bp = 0;

            });
    }
}
document.getElementById("loginForm").addEventListener('submit',handleLoginFormSubmit);

const handleInputChange = (evt) => {
    const obj = {}
    obj[evt.currentTarget.id] = evt.currentTarget.value;
    validateFormData(obj);
}
const allInputsValues = {};
document.querySelector("#loginForm #submitBtn").setAttribute("disabled",true);
document.querySelectorAll('#loginForm input').forEach(inputElement => {
    inputElement.addEventListener('change', handleInputChange);
    allInputsValues[inputElement.id] = false;
})