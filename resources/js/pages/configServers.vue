<template>
    <g-layout>
        <v-card class="card-100 ml-14">
            <v-card-title>
                <div>Сервера</div>
                <v-spacer></v-spacer>
                <v-dialog v-model="dialog" max-width="1000px">
                    <template v-slot:activator="{ on }">
                        <v-btn color="primary" dark class="mb-2" v-on="on" @click="addNew">Добавить сервер</v-btn>
                    </template>
                    <v-card>
                        <v-card-title>
                            <span class="headline">{{ formTitle }}</span>
                            <v-spacer />
                            <v-btn color="red" class="white--text" @click="deleteItem">Удалить</v-btn>
                        </v-card-title>
    
                        <v-card-text>
                            <v-form ref="addServer" @submit.prevent="submit">
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
                    :items="['system']"
                >
                    <v-skeleton-loader v-if="dictionaryLoading" type="table"/>
                    <v-data-table
                        v-else
                        :headers="headersWithControls"
                        :items="servers"
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

const REQUIRED = (v) => !!v || 'обязательно для заполнения'

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
            system_id: '',
            title: '',
            os: '',
            ip: '',
        },
        defaultItem: {
            id: '',
            system_id: 'as004',
            title: '',
            os: '',
            ip: '',
        },
        filter: {},
        tablePageSort: {"page":1,"itemsPerPage":15,"sortBy":["title"],"sortDesc":[false],"sortAsc":[true],"groupBy":[],"groupDesc":[],"mustSort":false,"multiSort":false},
        servers: [],
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
            if (this.isNew) {
                return this.dictionaryLoading?{}:{
                    id: '',
                    system_id: (this.dictionary?.system?.length > 0) ? this.dictionary.system[0].value : null,
                    title: '',
                    os: '',
                    ip: '',
                }
            } else {
                return this.editedItem
            }
        },
        formTitle () {
            return this.editedIndex === -1 ? 'Добавление сервера' : 'Редактирование сервера'
        },
        headersWithControls(){
            return [{value: 'controls_edit', sortable: false},...this.headers]
        },
        formSchema() {
            return this.dictionaryLoading?{}:{
                system_id: {
                    type:'autocomplete', label:'АС',
                    items: this.dictionary.system,
                    col: 12,
                    rules: [REQUIRED],
                    validateOnBlur: true
                },
                title: {type:'text', label:'Сервер', col: 12, rules: [REQUIRED], validateOnBlur: true},
                ip: {type:'text', label:'IP', col: 12},
                os: {type:'text', label:'ОС', col: 12},
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
        },
        dialog: function(val) {
            //отслеживать открытие диалога
        }
    },

    methods: {
        initialize () {
            this.servers = this.data?.items ?? [];
            this.load = false;
        },

        getDictionary(title){
            return this.dictionary?.[title] ?? []
        },

        itemValue(item, header){
            let val = item[header.value]
            if(header.dictionary){
                return  this.getDictionary(header.dictionary)?.find(v => v.value == val)?.text
            }
            return  val
        },

        editItem (id) {
            this.isNew = false;
            const indexes = this.servers.map(el => el.id);
            this.editedIndex = indexes.indexOf(id);
            this.editedItem = Object.assign({}, this.servers[indexes.indexOf(id)])
            this.dialog = true
        },

        addNew () {
            this.isNew = true;
            this.editedItem = this.defaultItem;
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
            if (e.key === 'system_id' || e.key === 'os') {
                this.defaultItem[e.key] = e.value;
            }
            if (this.editedItem['system_id'] === ''){
                this.editedItem['system_id'] = this.defaultItem['system_id'];
            }
        },

        async submit() {
            this.load = true;
            this.requestStarted = true;
            await axios.post('/serverSave', {...this.editedItem}).then((response) => {
                this.requestStarted = false;
                if (response.data.success !== undefined) {
                    this.close();
                    this.reload();
                    SweetAlert.toast(response.data.success);
                    this.$refs.addServer.inputs[1].reset();  //очищаем поле Сервер
                    this.$refs.addServer.inputs[2].reset();  //очищаем поле Ip
                }
                if (response.data.error !== undefined) {
                    SweetAlert.toast(response.data.error, 'error');
                    if (response.data.text !== undefined) {
                        SweetAlert.toast(response.data.text, 'error');
                    }
                }
            });
        },
    
        async deleteItem(){
            let item = this.servers[this.editedIndex];
            let confirm = await SweetAlert.confirm('Вы уверены?')
            if(confirm){
                this.load = true;
                let res = await axios.delete('/server_resource/'+item.id);
                if(res.data.success){
                    this.show = false
                    SweetAlert.toast(res.data.success);
                } else {
                    SweetAlert.toast(res.data.error, 'error');
                    console.error(res?.data?.trace);
                }
                this.reload();
                this.close();
                this.$refs.addServer.inputs[1].reset();  //очищаем поле Сервер
                this.$refs.addServer.inputs[2].reset();  //очищаем поле Ip
            }
        }
    }
}
</script>

<style>
.v-data-table-header th[role="columnheader"]:not(.sortable) {
    position: relative;
}
</style>