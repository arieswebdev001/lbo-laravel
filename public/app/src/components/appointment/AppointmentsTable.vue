<template>
    <div>
        <data-table
            :columns="appointmentTable.columns"
            :rows="appointments"
            :paginate="paginate"
            :onClick="appointmentTable.rowClicked"
            styleClass="table table-bordered table-hover table-striped"
        />
    </div>
</template>

<script>
    import DataTable from '../tables/DataTable.vue';

    export default {
        name: 'ActiveAppointments',
        components:{ DataTable },
        props:['appointments', 'hide_client', 'hide_branch','paginate'],
        data: function(){
            return {
                appointmentTable:{
                    columns: [
                        { label: 'Client', field: 'client_name', hidden: this.hide_client },
                        { label: 'Branch', field: 'branch_name', hidden: this.hide_branch },
                        { label: 'Technician', field: 'technician_name' },
                        { label: 'App. Date', field: 'transaction_date_formatted' },
                        { label: 'App. Time', field: 'transaction_time_formatted' },
                        { label: 'App. Added', field: 'transaction_added_formatted' },
                        { label: 'Status', field: 'status_formatted', html:true },
                    ],
                    rowClicked: this.viewAppointment,
                },
                display_appointment:{}
            }
        },
        methods:{
            viewAppointment:function(appointment) {
                this.display_appointment = appointment;
                this.$store.commit('appointments/updateViewingID', appointment.id);
                $("#appointment-modal").modal("show");
            }
        }
    }
</script>