<template>
    <div class="messages">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <i class="icon-puzzle font-grey-gallery"></i>
                    <span class="caption-subject bold font-grey-gallery uppercase"> {{ title }} </span>
                </div>
                <div class="tools">
                    <a href="" class="collapse" data-original-title="" title=""> </a>
                    <a href="" class="reload" data-original-title="" title=""> </a>
                    <a href="" class="fullscreen" data-original-title="" title=""> </a>
                </div>
            </div>
            <div class="portlet-body">

            </div>
        </div>
    </div>
</template>

<script>
    export default {
        name: 'Messages',
        data: function(){
            return {
                title: 'Messages',
            }
        },
        methods:{

        },
        mounted:function(){
            this.$emit('update_title', this.title);
            this.$emit('update_user');
        }
    }
</script>