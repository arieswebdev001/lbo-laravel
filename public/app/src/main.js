import Vue from 'vue';
import App from './App.vue';
import router from './router';

Vue.config.productionTip = false;
Vue.config.debug = true;
Vue.config.devtools = true;
var main = new Vue({
    el: '#app',
    router,
    template: '<App/>',
    components: { App }
});
