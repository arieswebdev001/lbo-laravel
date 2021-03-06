import Vue from 'vue';
import App from './components/App.vue';
import router from './router';
import VueSocketIO from 'vue-socket.io';
import {store} from './store/store';

Vue.config.productionTip = false;
Vue.config.debug = true;
Vue.config.devtools = true;
import {mixins} from './mixins';

//frontend configurations
let client_socket = 'https://socket.lay-bare.com';
//end frontend configurations

Vue.use(VueSocketIO, client_socket);
Vue.mixin(mixins);
new Vue({
    el: '#app',
    router,
    store,
    template: '<App/>',
    components: { App }
});