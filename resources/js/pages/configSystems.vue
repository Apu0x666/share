<template>
    <g-layout>
        <v-card class="card-100 ml-14">
            <v-card-title>
                <div>Системы</div>
                <v-spacer></v-spacer>
                <v-dialog v-model="dialog" max-width="1000px">
                    <v-card>
                        <v-card-title>
                            <span class="headline">{{ formTitle }}</span>
                        </v-card-title>
    
                        <v-card-text>
                            <v-form @submit.prevent="submit">
                                <v-form-base :model="formModel" :schema="formSchema" @input="formChange"/>
                                <v-card-actions>
                                    <v-btn plain @click="close">Отмена</v-btn>
                                    <v-spacer />
                                    <v-btn type="submit" color="primary" :disabled="submitDisabled">Сохранить</v-btn>
                                </v-card-actions>
                            </v-form>
                        </v-card-text>
                    </v-card>
                </v-dialog>
            </v-card-title>
            <v-card-text>
                <dictionary-loader
                    :items="['system', 'mailServers']"
                >
                    <v-skeleton-loader v-if="dictionaryLoading" type="table"/>
                    <v-data-table
                        v-else
                        :headers="headersWithControls"
                        :items="systems"
                        :server-items-length="count"
                        :options.sync="tablePageSort"
                        :loading="waitingIndicator"
                        :footer-props="{'items-per-page-options': [15, 25,50,100,250,500]}"
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
                            <template v-if="header.value === 'controls_edit'">
                                <td class="justify-center">
                                    <v-icon
                                        small
                                        class="mr-2"
                                        @click="editItem(item.id)"
                                    >
                                        mdi-pencil
                                    </v-icon>
                                </td>
                            </template>
                            <template v-else>
                                {{itemValue(item, header)}}
                            </template>
                        </template>
        
                        <template v-slot:no-data>
                            <v-btn color="primary" @click="initialize">Обновить</v-btn>
                        </template>
                    </v-data-table>
                </dictionary-loader>
            </v-card-text>
        </v-card>
    </g-layout>
</template>

<script>
import {
    mapState
} from "vuex";
import {
    Inertia
} from "@inertiajs/inertia";
import axios
    from "axios";
import {
    SweetAlert
} from "../helpers/SweetAlert";

export default {
    name: "Home",
    components: {},
    props: [
        'data', 'headers'
    ],

    data: () => ({
        isNew: false,
        dialog: false,
        editedIndex: -1,
        requestStarted: false,
        item: [],
        editedItem: {
            id: '',
            title: '',
            address: '',
            api: '',
        },
        defaultItem: {
            id: '',
            title: '',
            mail_server_id: 0,
            address: '',
            api: '',
        },
        filter: {},
        tablePageSort: {"page":1,"itemsPerPage":100,"sortBy":["title"],"sortDesc":[false],"sortAsc":[true],"groupBy":[],"groupDesc":[],"mustSort":false,"multiSort":false},
        systems: [],
    }),

    computed: {
        ...mapState('dictionary', ['dictionary', 'dictionaryLoading']),
        count(){
            return this.data?.count ?? -1
        },
        submitDisabled(){
            return this.requestStarted
        },
        formModel(){
            return this.editedItem;
        },
        formTitle () {
            return this.editedIndex === -1 ? 'Добавление системы' : 'Редактирование системы'
        },
        headersWithControls(){
            return [{value: 'controls_edit', sortable: false},...this.headers]
        },
        formSchema() {
            return this.dictionaryLoading?{}:{
                title: {type:'text', label:'Название', col: 12},
                address: {type:'text', label:'Адрес', col: 12},
                mail_server_id: {
                    type:'autocomplete', label:'Почтовый сервер',
                    items: this.dictionary.mailServers,
                    multiple: false,
                    chips: false,
                    clearable: true,
                    col: 12
                },
                api: {type:'text', label:'Ссылка на API', col: 12},
            }
        },
        waitingIndicator() {
            return (this.load === true) ? 'loading' : false;
        }
    },

    mounted() {
        this.initialize()

        if(this.isNew){
            this.editedItem = {
                ...this.defaultItem
            }
        }
    },

    watch: {
        data(){
            this.initialize()
        },
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
        initialize () {
            this.systems = this.data?.items ?? [];
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
            return val
        },

        editItem (id) {
            this.isNew = false;
            const indexes = this.systems.map(el => el.id);
            this.editedIndex = indexes.indexOf(id);
            this.editedItem = Object.assign({}, this.systems[indexes.indexOf(id)])
            this.dialog = true
        },

        addNew () {
            this.isNew = true;
            this.editedItem = Object.assign({}, this.defaultItem)
        },

        close () {
            this.isNew = true;
            this.dialog = false;
        },

        reload(){
            this.load = true;
            this.loadData().then(() => {
                this.initialize();
            });
        },

        async loadData() {
            this.load = true;
            Inertia.reload({
                only: ['data'],
                method: 'post',
                data: {
                    tablePageSort: this.tablePageSort,
                    filter: this.filter,
                }
            })
        },

        formChange(e){
            this.editedItem[e.key] = e.value
        },

        async submit(){
            this.load = true;
            this.requestStarted = true;
            await axios.post('/systemSave', {...this.editedItem}).then((response) => {
                this.requestStarted = false;
                this.close();
                if (response.data.success !== undefined) {
                    this.reload();
                    SweetAlert.toast(response.data.success);
                }
                if (response.data.error !== undefined) {
                    SweetAlert.toast(response.data.error, 'error');
                }
            });
        },
    }
}
</script>

<style>
.v-data-table-header th[role="columnheader"]:not(.sortable) {
    position: relative;
}
</style>