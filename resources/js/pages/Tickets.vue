<template xmlns="http://www.w3.org/1999/html">
    <g-layout>
        <v-card class="card-100 ml-14">
            <v-card-title>
                <div>Заявки</div>
                <v-spacer/>
                <xlsx-import-modal
                        @change="onImport"
                >Импортировать XLSX</xlsx-import-modal>
                <inertia-link as="v-btn" href="/tickets/create">Добавить заявку</inertia-link>
                <v-btn @click="reload" class="mx-2">Обновить</v-btn>
                <v-btn @click="sync" :loading="syncButtonDisabled" :disabled="syncButtonDisabled">Синхронизовать</v-btn>
            </v-card-title>
            <v-card-text>
                <dictionary-loader
                    :items="[
                        'applicant',
                        'priority',
                        'server',
                        'service_type',
                        'software',
                        'source',
                        'status',
                        'system',
                        'user'
                        ]"
                >
                    <v-skeleton-loader v-if="dictionaryLoading" type="table"/>
                    <v-data-table
                        v-else
                        :headers="headersWithControls"
                        :items="items"
                        show-expand
                        show-select
                        :expanded.sync="expanded"
                        selectable-key="id"
                        :item-class="itemClass"
                        v-model="selected"
                        :options.sync="tablePageSort"
                        :server-items-length="count"
                        :footer-props="{'items-per-page-options': [15,25,50,100,250,500]}"
                        @item-expanded="onExpandTicket"
                        :loading="waitingIndicator"
                    >
                        <template v-for="header in headersWithControls" v-slot:[`header.${header.value}`]>
                            <div style="min-height: 80px;">
                                <div >{{header.text}}</div>
                                <div v-if="header.dictionary" @click.stop.prevent>
                                    <v-autocomplete
                                        :items="getDictionary(header.dictionary)"
                                        v-model="filter[header.value]"
                                        clearable multiple
                                    />
                                </div>
                            </div>
                        </template>
                        <template v-for="header in headersWithControls" v-slot:[`item.${header.value}`]="{item}">
                            <template v-if="header.value === 'controls'">
                                <inertia-link
                                    :id="'ticket-'+item.id"
                                    as="v-btn" icon
                                    :href="`/tickets/${item.id}/edit`"
                                ><v-icon small>mdi-pencil</v-icon></inertia-link>
                            </template>
                            <template v-else-if="typeof item[header.value] === 'boolean'">
                                <v-simple-checkbox
                                    :color="item[header.value]?'green':'red'"
                                    v-model="item[header.value]"
                                    disabled
                                ></v-simple-checkbox>
                            </template>
                            <template v-else>
                                {{itemValue(item, header)}}
                            </template>
                        </template>
                        <template v-slot:expanded-item="{ headers, item }">
                            <td :colspan="headers.length" class="pa-5">
                                <v-card>
                                    <v-card-title>Описание</v-card-title>
                                    <v-card-text v-html="item.content"/>
                                </v-card>
                                <v-card class="my-5">
                                    <v-card-title>Отчет</v-card-title>
                                    <v-card-text v-html="item.report"/>
                                </v-card>
                                <TicketServicesTable
                                    v-if="services && services[item.id]"
                                    :ticket-id="item.id"
                                    :ticket-title="item.title"
                                    :headers="serviceHeaders" :items="services[item.id]"
                                    @change="item.synced = false; change();"
                                    @afterSync="afterSync"
                                />
                                <v-skeleton-loader v-else type="table"/>
                            </td>
                        </template>
                        <template #footer.prepend>
                            Выбрано {{selectedCount}} строк:
<!--                            <v-btn-->
<!--                                :disabled="selected.length < 1"-->
<!--                                class="ma-2" x-small color="blue"-->
<!--                            >Отправить отчёт</v-btn>-->
                            <v-btn
                                :disabled="selectedCount < 1 || batchSyncButtonDisabled"
                                class="ma-2" x-small color="green"
                                @click="syncTickets"
                                :loading="batchSyncButtonDisabled"
                            >Синхронизировать</v-btn>
                            <v-btn
                                @click.prevent="batchDelete"
                                :disabled="selectedCount < 1"
                                class="ma-2 white--text" x-small color="red"
                            >Удалить</v-btn>
                        </template>
                    </v-data-table>
                </dictionary-loader>
            </v-card-text>
        </v-card>
        <v-snackbar
            elevation="20"
            color="primary"
            v-model="showMessage"
            :timeout="3000"
            absolute right bottom multi-line outlined
        ><pre>{{message}}</pre></v-snackbar>
    </g-layout>
</template>
<script>

import TicketServicesTable from "../components/Tickets/TicketServicesTable";
import XlsxImportModal from "../components/Import/XlsxImportModal";
import {mapState} from "vuex";
import {Inertia} from "@inertiajs/inertia";
import axios from "axios";

export default {
    name: "Home",
    components: {TicketServicesTable, XlsxImportModal},
    props: [
        'data', 'headers', 'serviceHeaders'
    ],
    remember: ['expanded'],
    data: () => ({
        load: false,
        syncButtonDisabled: false,
        batchSyncButtonDisabled: false,
        expanded: [],
        selected:[],
        filter: {
           status_id: []
        },
        tablePageSort: {"page":1,"itemsPerPage":15,"sortBy":["bitrix_id"],"sortDesc":[true],"groupBy":[],"groupDesc":[],"mustSort":false,"multiSort":false},
        message: 'Тестовое сообщение',
        showMessage: false,
        services: {}
    }),
    computed: {
        ...mapState('dictionary', ['dictionary', 'dictionaryLoading']),
        selectedCount(){
            return this.selected?.length ?? 0
        },
        headersWithControls(){
            return [{value: 'controls', sortable: false},...this.headers]
        },
        count(){
            return this.data?.count ?? -1
        },
        items(){
            const tickets = this.data?.items ?? [];
            for (let ticket of tickets) {
                ticket.applicant_id = Number(ticket.applicant_id);
            }
            return tickets
        },
        selectedIds(){
            return this.selected?.map(v => v.id)
        },
        expandedTicketIds(){
            return this.expanded?.map(v => v.id) ?? []
        },
        waitingIndicator() {
            return (this.load === true) ? 'loading' : false;
        }
    },
    mounted() {
        let [type, hash] = window.location.hash.split('-')
        if(type === '#ticket'){
            let item = this.items.find(i => parseInt(i.id) === parseInt(hash))
            if(item) {
                this.expanded.push(item);
                document.querySelector(`#ticket-${item.id}`)?.scrollIntoView();
            }
        }
    },
    watch: {
        tablePageSort(){
            this.loadData()
        },
        filter: {
            deep: true,
            handler() {
                this.loadData()
            }
        },
        load(enable) {
            return (enable) ? 'loading' : '';
        }
    },
    methods: {
        async onExpandTicket({item, value}){
            if(value) {
                await this.loadServices([item.id])
            } else {
                delete this.services[item.id]
            }
        },
        loadData(){
            this.load = true;
            Inertia.reload({
                only: ['data'],
                method: 'post',
                data: {
                    tablePageSort: this.tablePageSort,
                    filter: this.filter,
                    ticketIds: this.expandedTicketIds
                }
            })
            this.loadServices()
        },
        change(){
            this.loadData();
        },
        async loadServices(ticketIds = null){
            if(!ticketIds) {
                ticketIds = this.expandedTicketIds
            }
            if(ticketIds?.length > 0) {
                let r = await axios.post('/load-services', {
                    ticketIds
                })
                let data = r.data ?? {}
                for (let [k, v] of Object.entries(data)) {
                    this.$set(this.services, k, v)
                }
            }
            this.load = false;
        },
        getDictionary(title){
            return  this.dictionary?.[title] ?? []
        },
        itemValue(item, header){
            let val = item[header.value]
            if(header.dictionary){
                return  this.getDictionary(header.dictionary)?.find(v => v.value == val)?.text
            }
            return  val
        },
        async batchDelete(){
            if(this.selectedIds.length) {
                await axios.delete('/tickets/0', {data: {ids: this.selectedIds}})
                this.selected = []
                this.reload()
            }
        },
        async syncTickets(){
            if(this.selectedIds.length) {
                this.batchSyncButtonDisabled = true
                let res = await axios.post('sync', {tickets_ids: this.selectedIds})
                this.batchSyncButtonDisabled = false
                this.afterSync(res)
            }
        },
        onImport(){
            this.reload()
            //this.$emit('change', какие_то_данные_если_нужны)
        },
        itemClass(item){
            if(item.deleted) {
                return 'deleted_item';
            } else if(item.synced){
                return 'default'
            } else {
                return 'unsynced_item'
            }
        },
        reload(){
            // this.$inertia.reload({only: ['data']})
            this.loadData()
        },
        async sync(){
            this.syncButtonDisabled = true
            let res = await axios.post('sync', {system_id: this.filter?.system_id ?? []})
            this.syncButtonDisabled = false
            this.afterSync(res)
        },
        afterSync(res = null){
            if(res?.data){
                this.selected = []
                console.log(res?.data)
                this.message = res?.data
                this.showMessage = true
            }
            this.reload()
        }
    }
}
</script>

<style>
    .v-data-table tbody > .unsynced_item > td{
        background: #FFF176;
        border-color: #F9A825;
    }
    .v-data-table tbody > .deleted_item > td{
        background: #FF8A80;
        border-color: #FF5252;
    }
</style>
